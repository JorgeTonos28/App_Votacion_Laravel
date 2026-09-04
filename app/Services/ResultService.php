<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Participant;
use App\Models\Vote;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Models\VotingResult;

class ResultService
{
    public function __construct(private readonly AuditService $audit) {}

    public function calculate(string $eventId, string $actorId): array
    {
        $event = VotingEvent::query()->with(['participants', 'groups', 'jurors'])->find($eventId);
        if (! $event) {
            throw new DomainException('EVENT_NOT_FOUND', 'No encontramos el evento.', 404);
        }
        $groups = $event->groups->where('enabled', true)->sortBy('sort_order')->values();
        if (abs((float) $groups->sum('weight') - 1) > .0001) {
            throw new DomainException('WEIGHTS_INVALID', 'Los pesos de los grupos no suman 100%.');
        }
        $round = (int) $event->current_round;
        $votes = Vote::query()->where('event_id', $eventId)->where('round_number', $round)->where('status', '!=', 'Invalidated')->get();
        $voterCount = Voter::query()->where('event_id', $eventId)->where('status', 'Active')->count();
        $jurors = $event->jurors->where('status', 'Active')->keyBy('id');
        VotingResult::query()->where('event_id', $eventId)->where('round_number', $round)->delete();
        $computed = [];
        foreach ($event->participants->where('status', '!=', 'Disqualified') as $participant) {
            $groupScores = [];
            foreach ($groups as $group) {
                $groupVotes = $votes->where('participant_id', $participant->id)->where('voting_group_id', $group->id);
                $score = 0.0;
                if ($groupVotes->isNotEmpty()) {
                    if ($group->role_type === 'Jury') {
                        $totalWeight = $groupVotes->sum(fn ($vote) => (float) ($jurors->get($vote->actor_id)?->individual_weight ?? 1));
                        $score = $totalWeight ? $groupVotes->sum(fn ($vote) => (float) $vote->normalized_score * (float) ($jurors->get($vote->actor_id)?->individual_weight ?? 1)) / $totalWeight : 0;
                    } else {
                        $score = (float) $groupVotes->avg('normalized_score');
                    }
                }
                $expected = $group->role_type === 'Jury' ? $jurors->count() : $voterCount;
                $quorum = ($group->minimum_votes === null || $groupVotes->count() >= $group->minimum_votes)
                    && ($group->minimum_participation_percent === null || $expected === 0 || $groupVotes->count() / $expected * 100 >= $group->minimum_participation_percent)
                    && (! $group->require_all_jurors || $group->role_type !== 'Jury' || $groupVotes->count() >= $jurors->count());
                $score = round($score, 4);
                $groupScores[$group->id] = ['score' => $score, 'voteCount' => $groupVotes->count(), 'quorum' => $quorum, 'role' => $group->role_type];
                VotingResult::query()->create(['event_id' => $eventId, 'round_number' => $round, 'participant_id' => $participant->id, 'voting_group_id' => $group->id, 'score' => $score, 'vote_count' => $groupVotes->count(), 'quorum_met' => $quorum]);
            }
            $final = round($groups->sum(fn ($group) => $groupScores[$group->id]['score'] * (float) $group->weight), 4);
            $computed[] = ['participant' => $participant, 'final' => $final, 'quorum' => collect($groupScores)->every(fn ($g) => $g['quorum']), 'groups' => $groupScores];
        }
        usort($computed, function ($a, $b) {
            return [$b['final'], $this->roleScore($b, 'Jury'), $this->roleVotes($b, 'Public'), -$b['participant']->presentation_order]
                <=> [$a['final'], $this->roleScore($a, 'Jury'), $this->roleVotes($a, 'Public'), -$a['participant']->presentation_order];
        });
        foreach ($computed as $index => $row) {
            $tied = count(array_filter($computed, fn ($x) => $x['final'] === $row['final'])) > 1;
            VotingResult::query()->create(['event_id' => $eventId, 'round_number' => $round, 'participant_id' => $row['participant']->id, 'score' => $row['final'], 'vote_count' => collect($row['groups'])->sum('voteCount'), 'quorum_met' => $row['quorum'], 'rank' => $index + 1, 'tie_status' => $tied ? 'Tied' : 'None']);
        }
        $this->audit->write($eventId, 'Administrator', $actorId, 'RESULTS_RECALCULATED', 'Event', $eventId, newValue: ['participants' => count($computed)]);

        return $this->ranking($eventId, $round);
    }

    public function ranking(string $eventId, ?int $round = null): array
    {
        $round ??= (int) VotingEvent::query()->whereKey($eventId)->value('current_round');
        $final = VotingResult::query()->where('event_id', $eventId)->where('round_number', $round)->whereNull('voting_group_id')->orderBy('rank')->get();
        $groupRows = VotingResult::query()->where('event_id', $eventId)->where('round_number', $round)->whereNotNull('voting_group_id')->get();
        $participants = Participant::query()->where('event_id', $eventId)->get()->keyBy('id');
        $event = VotingEvent::query()->with('groups')->findOrFail($eventId);
        $groups = $event->groups->keyBy('id');

        return $final->map(function ($result) use ($groupRows, $participants, $groups) {
            $rows = $groupRows->where('participant_id', $result->participant_id);
            $jury = $rows->first(fn ($r) => $groups->get($r->voting_group_id)?->role_type === 'Jury');
            $public = $rows->first(fn ($r) => $groups->get($r->voting_group_id)?->role_type === 'Public');
            $p = $participants[$result->participant_id];

            return ['rank' => $result->rank, 'participantId' => $p->id, 'participantName' => $p->name, 'projectTitle' => $p->project_title, 'juryScore' => (float) ($jury?->score ?? 0), 'publicScore' => (float) ($public?->score ?? 0), 'finalScore' => (float) $result->score, 'juryVotes' => $jury?->vote_count ?? 0, 'publicVotes' => $public?->vote_count ?? 0, 'quorumMet' => $result->quorum_met, 'tieStatus' => $result->tie_status];
        })->all();
    }

    public function publish(string $eventId, string $actorId): void
    {
        $event = VotingEvent::query()->find($eventId);
        if (! $event) {
            throw new DomainException('EVENT_NOT_FOUND', 'No encontramos el evento.', 404);
        }
        $round = (int) $event->current_round;
        $final = VotingResult::query()->where('event_id', $eventId)->where('round_number', $round)->whereNull('voting_group_id')->get();
        if ($final->isEmpty()) {
            throw new DomainException('RESULTS_NOT_READY', 'Debes calcular los resultados antes de publicar.');
        }
        if ($event->require_quorum_to_publish && $final->contains(fn ($r) => ! $r->quorum_met)) {
            throw new DomainException('QUORUM_NOT_MET', 'No se alcanzó el quórum requerido.');
        }
        VotingResult::query()->where('event_id', $eventId)->where('round_number', $round)->update(['published_at' => now()]);
        $event->update(['status' => 'Published']);
        $this->audit->write($eventId, 'Administrator', $actorId, 'RESULTS_PUBLISHED', 'Event', $eventId);
    }

    public function unpublish(string $eventId, string $actorId): void
    {
        $event = VotingEvent::query()->findOrFail($eventId);
        VotingResult::query()->where('event_id', $eventId)->where('round_number', $event->current_round)->update(['published_at' => null]);
        $event->update(['status' => 'Finished']);
        $this->audit->write($eventId, 'Administrator', $actorId, 'RESULTS_UNPUBLISHED', 'Event', $eventId);
    }

    private function roleScore(array $row, string $role): float
    {
        foreach ($row['groups'] as $g) {
            if ($g['role'] === $role) {
                return $g['score'];
            }
        }

return 0;
    }

    private function roleVotes(array $row, string $role): int
    {
        foreach ($row['groups'] as $g) {
            if ($g['role'] === $role) {
                return $g['voteCount'];
            }
        }

return 0;
    }
}
