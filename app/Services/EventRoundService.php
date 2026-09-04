<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Presentation;
use App\Models\VotingEvent;
use Illuminate\Support\Facades\DB;

class EventRoundService
{
    public function __construct(private readonly AuditService $audit) {}

    public function restart(string $eventId, string $actorId): int
    {
        return DB::transaction(function () use ($eventId, $actorId) {
            $event = VotingEvent::query()->lockForUpdate()->findOrFail($eventId);
            $previousRound = (int) $event->current_round;
            $nextRound = $previousRound + 1;
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
                'version' => $event->version + 1,
            ]);
            $this->audit->write($event->id, 'Administrator', $actorId, 'EVENT_ROUND_RESTARTED', 'Event', $event->id, ['round' => $previousRound], ['round' => $nextRound, 'participants' => $participants->count()]);

            return $nextRound;
        });
    }
}
