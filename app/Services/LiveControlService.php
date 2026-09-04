<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\VotingEvent;
use Illuminate\Support\Facades\DB;

class LiveControlService
{
    public function __construct(private readonly AuditService $audit) {}

    public function operate(string $eventId, string $operation, string $actorId, ?string $participantId = null, ?string $presentationId = null, ?string $reason = null): void
    {
        match (strtolower($operation)) {
            'lobby' => $this->changeStatus($eventId, ['Draft', 'Scheduled'], 'LobbyOpen', 'LOBBY_OPENED', $actorId),
            'start' => $this->changeStatus($eventId, ['LobbyOpen', 'Scheduled'], 'Live', 'EVENT_STARTED', $actorId),
            'presentation' => $this->startPresentation($eventId, (string) $participantId, $actorId),
            'open' => $this->openVoting($eventId, (string) $presentationId, $actorId),
            'close' => $this->closeVoting($eventId, (string) $presentationId, $actorId),
            'reopen' => $this->reopenVoting($eventId, (string) $presentationId, (string) $reason, $actorId),
            'pause' => $this->pause($eventId, $actorId),
            'resume' => $this->resume($eventId, $actorId),
            'finish' => $this->finish($eventId, $actorId),
            default => throw new DomainException('INVALID_OPERATION', 'La operación no es válida.'),
        };
    }

    public function startPresentation(string $eventId, string $participantId, string $actorId): void
    {
        DB::transaction(function () use ($eventId, $participantId, $actorId) {
            $event = VotingEvent::query()->with('presentations.participant')->lockForUpdate()->find($eventId);
            if (! $event) {
                throw $this->notFound();
            }
            if (! in_array($event->status, ['Live', 'LobbyOpen'], true)) {
                throw $this->invalidTransition();
            }
            $presentations = $event->presentations->where('round_number', $event->current_round);
            if ($presentations->contains(fn ($p) => in_array($p->status, ['OnStage', 'VotingOpen'], true))) {
                throw new DomainException('CONCURRENT_UPDATE', 'Ya existe una presentación activa.', 409);
            }
            $presentation = $presentations->firstWhere('participant_id', $participantId);
            if (! $presentation) {
                throw new DomainException('PARTICIPANT_NOT_FOUND', 'No encontramos el participante.', 404);
            }
            if ($presentation->participant?->status === 'Disqualified') {
                throw new DomainException('PARTICIPANT_DISQUALIFIED', 'El equipo está inhabilitado y no puede pasar al escenario.', 409);
            }
            if (in_array($presentation->status, ['Disqualified', 'Scored'], true)) {
                throw $this->invalidTransition();
            }
            $previous = $presentation->status;
            $presentation->update(['status' => 'OnStage', 'stage_started_at' => now(), 'timer_paused_at' => null, 'paused_timer_seconds' => 0, 'version' => $presentation->version + 1]);
            $event->update(['status' => 'Live', 'active_presentation_id' => $presentation->id]);
            $this->audit->write($eventId, 'Operator', $actorId, 'PRESENTATION_STARTED', 'Presentation', $presentation->id, $previous, 'OnStage');
        });
    }

    public function openVoting(string $eventId, string $presentationId, string $actorId): void
    {
        $event = VotingEvent::query()->with('presentations')->find($eventId);
        if (! $event) {
            throw $this->notFound();
        }
        if ($event->status !== 'Live' || $event->active_presentation_id !== $presentationId) {
            throw $this->invalidTransition();
        }
        $p = $event->presentations->firstWhere('id', $presentationId);
        if (! $p || ! in_array($p->status, ['OnStage', 'VotingClosed'], true)) {
            throw $this->invalidTransition();
        }
        $p->update(['status' => 'VotingOpen', 'stage_ended_at' => $p->stage_ended_at ?: now(), 'voting_opened_at' => now(), 'voting_closed_at' => null, 'timer_paused_at' => null, 'paused_timer_seconds' => 0, 'version' => $p->version + 1]);
        $this->audit->write($eventId, 'Operator', $actorId, 'VOTING_OPENED', 'Presentation', $presentationId);
    }

    public function closeVoting(string $eventId, string $presentationId, string $actorId): void
    {
        DB::transaction(function () use ($eventId, $presentationId, $actorId) {
            $event = VotingEvent::query()->with('presentations')->lockForUpdate()->find($eventId);
            if (! $event) {
                throw $this->notFound();
            }
            if ($event->active_presentation_id !== $presentationId) {
                throw $this->invalidTransition();
            }
            $p = $event->presentations->firstWhere('id', $presentationId);
            if (! $p || $p->status !== 'VotingOpen') {
                throw new DomainException('VOTING_CLOSED', 'La votación ya fue cerrada.', 409);
            }
            $p->update(['status' => 'VotingClosed', 'voting_closed_at' => now(), 'version' => $p->version + 1]);
            $event->update(['active_presentation_id' => null]);
            $this->audit->write($eventId, 'Operator', $actorId, 'VOTING_CLOSED', 'Presentation', $presentationId);
        });
    }

    public function reopenVoting(string $eventId, string $presentationId, string $reason, string $actorId): void
    {
        if (mb_strlen(trim($reason)) < 8) {
            throw new DomainException('REASON_REQUIRED', 'Debes indicar el motivo de reapertura.');
        }
        $event = VotingEvent::query()->with('presentations')->find($eventId);
        if (! $event) {
            throw $this->notFound();
        }
        if ($event->active_presentation_id && $event->active_presentation_id !== $presentationId) {
            throw new DomainException('CONCURRENT_UPDATE', 'Existe otra presentación activa.', 409);
        }
        $p = $event->presentations->firstWhere('id', $presentationId);
        if (! $p || ! in_array($p->status, ['VotingClosed', 'Scored'], true)) {
            throw $this->invalidTransition();
        }
        $p->update(['status' => 'VotingOpen', 'voting_opened_at' => now(), 'voting_closed_at' => null, 'timer_paused_at' => null, 'paused_timer_seconds' => 0, 'version' => $p->version + 1]);
        $event->update(['active_presentation_id' => $presentationId, 'status' => 'Live']);
        $this->audit->write($eventId, 'Operator', $actorId, 'VOTING_REOPENED', 'Presentation', $presentationId, note: trim($reason));
    }

    public function pause(string $eventId, string $actorId): void
    {
        $event = VotingEvent::query()->with('presentations')->find($eventId);
        if (! $event) {
            throw $this->notFound();
        } if ($event->status !== 'Live') {
            throw $this->invalidTransition();
        }
        $p = $event->presentations->firstWhere('id', $event->active_presentation_id);
        if ($p && in_array($p->status, ['OnStage', 'VotingOpen'], true)) {
            $p->update(['timer_paused_at' => now(), 'version' => $p->version + 1]);
        }
        $event->update(['status' => 'Paused']);
        $this->audit->write($eventId, 'Operator', $actorId, 'EVENT_PAUSED', 'Event', $eventId, newValue: ['presentationId' => $p?->id]);
    }

    public function resume(string $eventId, string $actorId): void
    {
        $event = VotingEvent::query()->with('presentations')->find($eventId);
        if (! $event) {
            throw $this->notFound();
        } if ($event->status !== 'Paused') {
            throw $this->invalidTransition();
        }
        $p = $event->presentations->firstWhere('id', $event->active_presentation_id);
        if ($p?->timer_paused_at) {
            $seconds = $p->timer_paused_at->diffInSeconds(now());
            $p->update(['paused_timer_seconds' => $p->paused_timer_seconds + $seconds, 'timer_paused_at' => null, 'version' => $p->version + 1]);
        }
        $event->update(['status' => 'Live']);
        $this->audit->write($eventId, 'Operator', $actorId, 'EVENT_RESUMED', 'Event', $eventId, newValue: ['presentationId' => $p?->id]);
    }

    public function finish(string $eventId, string $actorId): void
    {
        $event = VotingEvent::query()->with('presentations')->find($eventId);
        if (! $event) {
            throw $this->notFound();
        } if ($event->presentations->where('round_number', $event->current_round)->contains(fn ($p) => $p->status === 'VotingOpen')) {
            throw new DomainException('VOTING_NOT_CLOSED', 'Cierra la votación activa antes de finalizar.');
        }
        $event->update(['active_presentation_id' => null, 'status' => 'Finished']);
        $this->audit->write($eventId, 'Operator', $actorId, 'EVENT_FINISHED', 'Event', $eventId);
    }

    private function changeStatus(string $eventId, array $allowed, string $target, string $action, string $actorId): void
    {
        $event = VotingEvent::query()->find($eventId);
        if (! $event) {
            throw $this->notFound();
        } if (! in_array($event->status, $allowed, true)) {
            throw $this->invalidTransition();
        }$previous = $event->status;
        $event->update(['status' => $target]);
        $this->audit->write($eventId, 'Operator', $actorId, $action, 'Event', $eventId, $previous, $target);
    }

    private function invalidTransition(): DomainException
    {
        return new DomainException('INVALID_TRANSITION', 'La acción no es válida para el estado actual.', 409);
    }

    private function notFound(): DomainException
    {
        return new DomainException('EVENT_NOT_FOUND', 'No encontramos el evento.', 404);
    }
}
