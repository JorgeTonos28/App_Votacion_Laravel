<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Vote;
use App\Models\VoteDetail;
use App\Models\VoteRevision;
use App\Models\VotingEvent;
use App\Models\VotingGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VoteService
{
    public function __construct(private readonly AuditService $audit) {}

    public function submit(array $session, string $presentationId, string $clientRequestId, array $answers): array
    {
        if (trim($clientRequestId) === '' || strlen($clientRequestId) > 100) {
            throw new DomainException('INVALID_BALLOT', 'La solicitud de voto no es válida.');
        }
        if ($receipt = $this->receiptByRequest($clientRequestId)) {
            return [...$receipt, 'wasIdempotent' => true];
        }

        try {
            return DB::transaction(function () use ($session, $presentationId, $clientRequestId, $answers) {
                $event = VotingEvent::query()->with('presentations.participant')->lockForUpdate()->findOrFail($session['eventId']);
                if ($event->status !== 'Live') {
                    throw new DomainException('VOTING_NOT_OPEN', 'La votación no está disponible en este momento.', 409);
                }
                $presentation = $event->presentations->firstWhere('id', $event->active_presentation_id);
                if (! $presentation || $presentation->id !== $presentationId) {
                    throw new DomainException('NO_ACTIVE_PRESENTATION', 'La presentación cambió. Actualiza la pantalla.', 409);
                }
                if ($presentation->status !== 'VotingOpen') {
                    throw new DomainException('VOTING_CLOSED', 'La votación ya fue cerrada.', 409);
                }
                $role = $session['actorType'] === 'Juror' ? 'Jury' : 'Public';
                $actorKey = strtolower($role === 'Jury' ? 'juror' : 'public').':'.str_replace('-', '', $session['actorId']);
                $group = VotingGroup::query()->with('criteria')->where('event_id', $event->id)->where('role_type', $role)->where('enabled', true)->first();
                if (! $group) {
                    throw new DomainException('INVALID_BALLOT', 'No existe una rúbrica activa para tu rol.');
                }
                $version = (int) $group->criteria->where('enabled', true)->max('rubric_version');
                $criteria = $group->criteria->where('enabled', true)->where('rubric_version', $version)->sortBy('sort_order')->values();
                $calculated = $this->validateAndCalculate($criteria->all(), $answers);
                $vote = Vote::query()->with(['details', 'revisions'])->where('presentation_id', $presentation->id)->where('actor_key', $actorKey)->first();
                $canEdit = $role === 'Jury'
                    ? $event->allow_juror_vote_edit && $group->allow_edit_until_close
                    : $event->allow_public_vote_edit && $group->allow_edit_until_close;
                $updated = $vote !== null;
                if ($vote) {
                    if (! $canEdit) {
                        throw new DomainException('ALREADY_VOTED', 'Ya registraste un voto para esta presentación.', 409);
                    }
                    VoteRevision::query()->create([
                        'vote_id' => $vote->id, 'revision_number' => $vote->revisions->count() + 1,
                        'snapshot_json' => json_encode(['rawScore' => $vote->raw_score, 'normalizedScore' => $vote->normalized_score, 'details' => $vote->details], JSON_UNESCAPED_UNICODE),
                    ]);
                    VoteDetail::query()->where('vote_id', $vote->id)->delete();
                    $vote->update([
                        'raw_score' => $calculated['raw'], 'normalized_score' => $calculated['normalized'],
                        'updated_at' => now(), 'status' => 'Updated', 'client_request_id' => $clientRequestId,
                    ]);
                } else {
                    $vote = Vote::query()->create([
                        'event_id' => $event->id, 'presentation_id' => $presentation->id, 'participant_id' => $presentation->participant_id,
                        'voting_group_id' => $group->id, 'actor_id' => $session['actorId'], 'actor_key' => $actorKey,
                        'role_type' => $role, 'rubric_version' => $version, 'raw_score' => $calculated['raw'],
                        'normalized_score' => $calculated['normalized'], 'session_id' => $session['sessionId'], 'client_request_id' => $clientRequestId,
                    ]);
                }
                foreach ($calculated['answers'] as $answer) {
                    VoteDetail::query()->create(['vote_id' => $vote->id, ...$answer]);
                }
                $this->audit->write($event->id, $role, $session['actorId'], $updated ? 'VOTE_UPDATED' : 'VOTE_SUBMITTED', 'Vote', $vote->id, newValue: ['presentationId' => $presentation->id, 'roleType' => $role, 'normalizedScore' => $calculated['normalized']], requestId: $clientRequestId);

                return [
                    'voteId' => $vote->id, 'presentationId' => $presentation->id, 'participantName' => $presentation->participant->name,
                    'normalizedScore' => (float) $calculated['normalized'], 'submittedAt' => $vote->submitted_at?->toIso8601String() ?? now()->toIso8601String(),
                    'wasIdempotent' => false, 'wasUpdated' => $updated,
                ];
            });
        } catch (QueryException) {
            if ($receipt = $this->receiptByRequest($clientRequestId)) {
                return [...$receipt, 'wasIdempotent' => true];
            }

            throw new DomainException('CONCURRENT_UPDATE', 'El estado cambió. Intenta nuevamente.', 409);
        }
    }

    public function invalidate(string $voteId, string $reason, string $administratorId): void
    {
        if (mb_strlen(trim($reason)) < 8) {
            throw new DomainException('INVALID_BALLOT', 'La invalidación requiere un motivo claro.');
        }
        $vote = Vote::query()->find($voteId);
        if (! $vote) {
            throw new DomainException('VOTE_NOT_FOUND', 'No encontramos ese voto.', 404);
        }
        if ($vote->status === 'Invalidated') {
            return;
        }
        $previous = ['status' => $vote->status, 'normalizedScore' => $vote->normalized_score];
        $vote->update(['status' => 'Invalidated', 'invalidated_at' => now(), 'invalidated_by' => $administratorId, 'invalidation_reason' => trim($reason)]);
        $this->audit->write($vote->event_id, 'Administrator', $administratorId, 'VOTE_INVALIDATED', 'Vote', $vote->id, $previous, ['status' => $vote->status, 'reason' => $vote->invalidation_reason]);
    }

    private function receiptByRequest(string $requestId): ?array
    {
        $vote = Vote::query()->with('presentation.participant')->where('client_request_id', $requestId)->first();
        if (! $vote) {
            return null;
        }

        return ['voteId' => $vote->id, 'presentationId' => $vote->presentation_id, 'participantName' => $vote->presentation->participant->name, 'normalizedScore' => (float) $vote->normalized_score, 'submittedAt' => $vote->submitted_at->toIso8601String(), 'wasIdempotent' => true, 'wasUpdated' => $vote->status === 'Updated'];
    }

    private function validateAndCalculate(array $criteria, array $answers): array
    {
        if (! $criteria) {
            throw new DomainException('INVALID_BALLOT', 'La rúbrica no contiene criterios.');
        }
        $map = [];
        foreach ($answers as $answer) {
            $id = $answer['criterion_id'] ?? $answer['criterionId'] ?? null;
            if (! $id || isset($map[$id])) {
                throw new DomainException('INVALID_BALLOT', 'Hay respuestas duplicadas.');
            }
            $map[$id] = $answer;
        }
        $raw = $normalized = $weight = 0.0;
        $calculated = [];
        foreach ($criteria as $criterion) {
            $answer = $map[$criterion->id] ?? null;
            if (! $answer) {
                if ($criterion->required) {
                    throw new DomainException('INVALID_BALLOT', "Debes responder: {$criterion->name}.");
                }

                continue;
            }
            $value = (float) ($answer['value'] ?? 0);
            if ($value < $criterion->scale_min || $value > $criterion->scale_max) {
                throw new DomainException('INVALID_BALLOT', "La respuesta de {$criterion->name} está fuera de escala.");
            }
            $comment = trim((string) ($answer['comment'] ?? ''));
            if ($criterion->comment_mode === 'Required' && $comment === '') {
                throw new DomainException('INVALID_BALLOT', "Debes comentar: {$criterion->name}.");
            }
            $range = (float) $criterion->scale_max - (float) $criterion->scale_min;
            if ($range <= 0) {
                throw new DomainException('INVALID_BALLOT', "La escala de {$criterion->name} no es válida.");
            }
            $normalizedValue = ($value - (float) $criterion->scale_min) / $range * 100;
            $criterionWeight = (float) $criterion->weight;
            $weighted = $normalizedValue * $criterionWeight;
            $raw += $value * $criterionWeight;
            $normalized += $weighted;
            $weight += $criterionWeight;
            $calculated[] = ['criterion_id' => $criterion->id, 'raw_value' => $value, 'normalized_value' => $normalizedValue, 'criterion_weight' => $criterionWeight, 'weighted_value' => $weighted, 'comment' => $comment ?: null];
        }
        if (abs($weight - 1) > .0001) {
            throw new DomainException('WEIGHTS_INVALID', 'Los pesos de la rúbrica no suman 100%.');
        }

        return ['raw' => round($raw, 4), 'normalized' => round($normalized, 4), 'answers' => $calculated];
    }
}
