<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Juror;
use App\Models\VotingEvent;
use App\Support\Domain;

class AccessService
{
    public function __construct(private readonly AuditService $audit) {}

    public function findAvailableEvent(string $eventCode): VotingEvent
    {
        $normalized = Domain::normalizeCode($eventCode);
        if (strlen($normalized) < 4 || strlen($normalized) > 12) {
            throw $this->eventNotFound();
        }
        $event = VotingEvent::query()->with('branding')->whereRaw('UPPER(code) = ?', [$normalized])->first();
        if (! $event) {
            throw $this->eventNotFound();
        }
        if (in_array($event->status, ['Finished', 'Published', 'Archived'], true)) {
            throw new DomainException('EVENT_FINISHED', 'El evento ya finalizó.', 410);
        }
        if (in_array($event->status, ['Draft', 'Scheduled'], true)) {
            throw new DomainException('EVENT_NOT_AVAILABLE', 'El acceso al evento aún no está habilitado.', 403);
        }

        return $event;
    }

    public function validateJuror(string $eventCode, string $jurorCode): Juror
    {
        $event = $this->findAvailableEvent($eventCode);
        $juror = Juror::query()->where('event_id', $event->id)->get()
            ->first(fn (Juror $candidate) => Domain::verifySecret($jurorCode, $candidate->code_hash));
        if (! $juror) {
            $this->audit->write($event->id, 'Juror', null, 'JUROR_ACCESS_REJECTED', 'Juror', null, note: 'Código de jurado inválido.');
            throw new DomainException('INVALID_JUROR_CODE', 'El código de jurado no es válido.', 401);
        }
        if ($juror->locked_until?->isFuture()) {
            throw new DomainException('RATE_LIMITED', 'El acceso está bloqueado temporalmente.', 429);
        }
        if ($juror->status === 'Revoked') {
            throw new DomainException('JUROR_CODE_REVOKED', 'El acceso del jurado fue revocado.', 403);
        }
        if ($juror->status !== 'Active' || ($juror->expires_at && $juror->expires_at->isPast())) {
            throw new DomainException('INVALID_JUROR_CODE', 'El código de jurado no está vigente.', 401);
        }
        $juror->update(['failed_attempts' => 0, 'locked_until' => null]);

        return $juror;
    }

    private function eventNotFound(): DomainException
    {
        return new DomainException('EVENT_NOT_FOUND', 'No encontramos un evento con ese código.', 404);
    }
}
