<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Presentation;
use App\Models\VotingEvent;
use Illuminate\Support\Facades\DB;

class EventRoundService
{
    public function __construct(private readonly AuditService $audit) {}

    public function restart(string $eventId, string $actorId, string $mode = 'next_round'): int
    {
        return DB::transaction(function () use ($eventId, $actorId, $mode) {
            $event = VotingEvent::query()->lockForUpdate()->findOrFail($eventId);
            $currentRound = (int) $event->current_round;

            if ($mode === 'same_round') {
                $presentations = Presentation::query()->where('event_id', $event->id)->where('round_number', $currentRound)->get();
                foreach ($presentations as $presentation) {
                    $presentation->update([
                        'status' => $presentation->status === 'Disqualified' ? 'Disqualified' : 'Pending',
                        'stage_started_at' => null,
                        'stage_ended_at' => null,
                        'voting_opened_at' => null,
                        'voting_closed_at' => null,
                        'timer_paused_at' => null,
                        'paused_timer_seconds' => 0,
                        'version' => $presentation->version + 1,
                    ]);
                }

                DB::table('votes')->where('event_id', $event->id)->where('round_number', $currentRound)->delete();
                DB::table('results')->where('event_id', $event->id)->where('round_number', $currentRound)->delete();

                $event->update([
                    'status' => 'Draft',
                    'active_presentation_id' => null,
                    'archived_at' => null,
                    'version' => $event->version + 1,
                ]);

                $this->audit->write($event->id, 'Administrator', $actorId, 'EVENT_ROUND_RESTARTED_SAME', 'Event', $event->id, ['round' => $currentRound], ['round' => $currentRound, 'mode' => 'same_round']);

                return $currentRound;
            }

            $nextRound = $currentRound + 1;
            $participants = Participant::query()->where('event_id', $event->id)->orderBy('presentation_order')->get();

            foreach ($participants as $participant) {
                Presentation::query()->create([
                    'event_id' => $event->id,
                    'participant_id' => $participant->id,
                    'round_number' => $nextRound,
                    'sequence' => $participant->presentation_order,
                    'status' => $participant->status === 'Disqualified' ? 'Disqualified' : 'Pending',
                ]);
            }

            $event->update([
                'current_round' => $nextRound,
                'status' => 'Draft',
                'active_presentation_id' => null,
                'archived_at' => null,
                'version' => $event->version + 1,
            ]);
            $this->audit->write($event->id, 'Administrator', $actorId, 'EVENT_ROUND_RESTARTED', 'Event', $event->id, ['round' => $currentRound], ['round' => $nextRound, 'participants' => $participants->count()]);

            return $nextRound;
        });
    }
}
