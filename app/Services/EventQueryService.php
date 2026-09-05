<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Vote;
use App\Models\VotingEvent;
use App\Models\VotingGroup;
use App\Support\Domain;

class EventQueryService
{
    public function liveState(array $session): array
    {
        return $this->buildLiveState($session['eventId'], $session['actorId'], $session['actorType']);
    }

    public function liveStateByCode(string $eventCode): array
    {
        $event = VotingEvent::query()->whereRaw('UPPER(code) = ?', [Domain::normalizeCode($eventCode)])->first();
        if (! $event) {
            throw new DomainException('EVENT_NOT_FOUND', 'No encontramos un evento con ese código.', 404);
        }

        return $this->buildLiveState($event->id);
    }

    public function ballot(array $session): array
    {
        $event = VotingEvent::query()->with('presentations.participant')->findOrFail($session['eventId']);
        if ($event->status === 'Paused') {
            throw new DomainException('EVENT_NOT_AVAILABLE', 'El evento está pausado.', 409);
        }
        if (in_array($event->status, ['Finished', 'Published', 'Archived'], true)) {
            throw new DomainException('EVENT_FINISHED', 'El evento ya finalizó.', 410);
        }
        $presentation = $event->presentations->firstWhere('id', $event->active_presentation_id);
        if (! $presentation) {
            throw new DomainException('NO_ACTIVE_PRESENTATION', 'No hay una presentación activa.', 409);
        }
        if ($presentation->status !== 'VotingOpen') {
            throw new DomainException('VOTING_NOT_OPEN', 'La votación aún no está abierta.', 409);
        }
        $role = $session['actorType'] === 'Juror' ? 'Jury' : 'Public';
        $group = VotingGroup::query()->with('criteria')->where('event_id', $event->id)->where('role_type', $role)->where('enabled', true)->orderBy('sort_order')->first();
        if (! $group) {
            throw new DomainException('INVALID_BALLOT', 'Este evento no tiene una rúbrica activa para tu rol.', 409);
        }
        $version = (int) $group->criteria->where('enabled', true)->max('rubric_version');
        $criteria = $group->criteria->where('enabled', true)->where('rubric_version', $version)->sortBy('sort_order')->values();
        $participant = $presentation->participant;

        $timer = $this->timer($event, $presentation);

        return [
            'presentationId' => $presentation->id, 'participantId' => $presentation->participant_id,
            'participantName' => $participant->name, 'projectTitle' => $participant->project_title,
            'participantNumber' => $participant->number, 'participantMembers' => $participant->member_names,
            'participantArea' => $participant->area, 'participantDescription' => $participant->description,
            'groupName' => $group->name, 'roleType' => $role,
            'votingClosesAt' => $timer['endsAt']?->toIso8601String(),
            'timerRemainingSeconds' => $timer['remaining'],
            'timerIsPaused' => $timer['paused'],
            'criteria' => $criteria,
        ];
    }

    private function buildLiveState(string $eventId, ?string $actorId = null, ?string $actorType = null): array
    {
        $event = VotingEvent::query()->with(['presentations.participant', 'jurors'])->findOrFail($eventId);
        $presentation = $event->presentations->firstWhere('id', $event->active_presentation_id);
        $participant = $presentation?->participant;
        $publicCount = $jurorCount = 0;
        $actorHasVoted = false;
        if ($presentation) {
            $base = Vote::query()->where('presentation_id', $presentation->id)->where('status', '!=', 'Invalidated');
            $publicCount = (clone $base)->where('role_type', 'Public')->count();
            $jurorCount = (clone $base)->where('role_type', 'Jury')->count();
            if ($actorId && $actorType) {
                $actorKey = strtolower($actorType === 'Juror' ? 'juror' : 'public').':'.str_replace('-', '', $actorId);
                $actorHasVoted = Vote::query()->where('presentation_id', $presentation->id)->where('actor_key', $actorKey)->exists();
            }
        }
        $timer = $this->timer($event, $presentation);

        return [
            'eventId' => $event->id, 'eventCode' => $event->code, 'eventName' => $event->name, 'eventStatus' => $event->status, 'roundNumber' => $event->current_round,
            'presentationId' => $presentation?->id, 'presentationStatus' => $presentation?->status,
            'participantName' => $participant?->name, 'projectTitle' => $participant?->project_title,
            'participantNumber' => $participant?->number, 'participantMembers' => $participant?->member_names ?? [],
            'participantArea' => $participant?->area, 'participantDescription' => $participant?->description,
            'participantFingerprint' => $participant ? Domain::technicalHash(implode('|', [$participant->name, $participant->project_title, $participant->area, $participant->description, $participant->members])) : null,
            'presentationDurationSeconds' => $event->presentation_duration_seconds, 'votingDurationSeconds' => $event->voting_duration_seconds,
            'stageStartedAt' => $presentation?->stage_started_at?->toIso8601String(), 'votingOpenedAt' => $presentation?->voting_opened_at?->toIso8601String(),
            'timerEndsAt' => $timer['endsAt']?->toIso8601String(), 'timerRemainingSeconds' => $timer['remaining'], 'timerIsPaused' => $timer['paused'],
            'publicVoteCount' => $publicCount, 'jurorVoteCount' => $jurorCount,
            'jurorTotal' => $event->jurors->where('status', 'Active')->count(), 'currentActorHasVoted' => $actorHasVoted,
            'resultsVisibility' => $event->results_visibility,
        ];
    }

    private function timer(VotingEvent $event, mixed $presentation): array
    {
        if (! $presentation || ! in_array($presentation->status, ['OnStage', 'VotingOpen'], true)) {
            return ['endsAt' => null, 'remaining' => null, 'paused' => false];
        }
        $started = $presentation->status === 'OnStage' ? $presentation->stage_started_at : $presentation->voting_opened_at;
        if (! $started) {
            return ['endsAt' => null, 'remaining' => null, 'paused' => false];
        }
        $baseDuration = $presentation->status === 'OnStage' ? (int) $event->presentation_duration_seconds : (int) $event->voting_duration_seconds;
        $totalDuration = $baseDuration + (int) ($presentation->extra_seconds ?? 0);
        $paused = ($event->status === 'Paused') || ($presentation->timer_paused_at !== null);
        $until = $presentation->timer_paused_at ?: now();
        $totalWallClock = max(0, (int) round($started->diffInSeconds($until)));
        $pausedSeconds = (int) ($presentation->paused_timer_seconds ?? 0);
        $elapsed = max(0, $totalWallClock - $pausedSeconds);
        $remaining = max(0, $totalDuration - $elapsed);

        return [
            'endsAt' => $paused ? null : now()->addSeconds($remaining),
            'remaining' => $remaining,
            'paused' => $paused,
        ];
    }
}
