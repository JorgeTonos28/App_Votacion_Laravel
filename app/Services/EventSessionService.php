<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\EventSession;
use App\Models\Juror;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Support\Domain;

class EventSessionService
{
    public function createPublic(VotingEvent $event, string $deviceId, ?string $credential, string $displayName, ?string $userAgent): array
    {
        $displayName = trim($displayName);
        if ($displayName === '') {
            throw new DomainException('DISPLAY_NAME_REQUIRED', 'Indica tu nombre para continuar.');
        }
        $deviceHash = Domain::technicalHash($deviceId);
        $voter = match ($event->public_access_mode) {
            'IndividualCode' => $this->findByPersonalCode($event->id, $credential),
            'AttendeeList' => $this->findByExternalId($event->id, $credential),
            'Hybrid' => $credential ? ($this->findByExternalId($event->id, $credential, false) ?? $this->findByPersonalCode($event->id, $credential, false)) : null,
            default => Voter::query()->where('event_id', $event->id)->where('device_hash', $deviceHash)->first(),
        };
        if (! $voter) {
            if (in_array($event->public_access_mode, ['IndividualCode', 'AttendeeList'], true)) {
                throw new DomainException('INVALID_PUBLIC_CREDENTIAL', 'El código personal o identificador no es válido.', 401);
            }
            $voter = Voter::query()->create([
                'event_id' => $event->id,
                'mode' => $event->public_access_mode === 'Hybrid' ? 'Device' : $event->public_access_mode,
                'device_hash' => $deviceHash,
                'display_name' => $displayName,
            ]);
        } else {
            if ($voter->status !== 'Active') {
                throw new DomainException('UNAUTHORIZED', 'Este acceso no está habilitado.', 403);
            }
            $voter->update(['last_access_at' => now(), 'device_hash' => $voter->device_hash ?: $deviceHash, 'display_name' => $displayName]);
        }

        return $this->createSession($event->id, $voter->id, 'Public', $userAgent, $deviceHash, 12);
    }

    public function createJuror(VotingEvent $event, Juror $juror, ?string $userAgent): array
    {
        $juror->update(['last_access_at' => now()]);

        return $this->createSession($event->id, $juror->id, 'Juror', $userAgent, null, 8);
    }

    public function validate(?string $token, string $expectedType): array
    {
        if (! $token) {
            throw new DomainException('SESSION_EXPIRED', 'Tu sesión expiró. Vuelve a ingresar.', 401);
        }
        $session = EventSession::query()->with('event')->where('token_hash', strtoupper(hash('sha256', $token)))->first();
        if (! $session || $session->actor_type !== $expectedType || $session->revoked_at || $session->expires_at->isPast()) {
            throw new DomainException('SESSION_EXPIRED', 'Tu sesión expiró. Vuelve a ingresar.', 401);
        }
        if ($session->last_seen_at->lt(now()->subMinute())) {
            $session->update(['last_seen_at' => now()]);
        }

        return ['sessionId' => $session->id, 'eventId' => $session->event_id, 'actorId' => $session->actor_id, 'actorType' => $session->actor_type, 'event' => $session->event];
    }

    public function revoke(?string $token): void
    {
        if (! $token) {
            return;
        }
        EventSession::query()->where('token_hash', strtoupper(hash('sha256', $token)))->update(['revoked_at' => now()]);
    }

    private function createSession(string $eventId, string $actorId, string $actorType, ?string $userAgent, ?string $deviceHash, int $hours): array
    {
        EventSession::query()->where('event_id', $eventId)->where('actor_id', $actorId)->where('actor_type', $actorType)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $token = Domain::token();
        $session = EventSession::query()->create([
            'event_id' => $eventId, 'actor_id' => $actorId, 'actor_type' => $actorType,
            'token_hash' => strtoupper(hash('sha256', $token)),
            'user_agent_hash' => Domain::technicalHash($userAgent), 'device_hash' => $deviceHash,
            'expires_at' => now()->addHours($hours),
        ]);

        return [$token, $session];
    }

    private function findByPersonalCode(string $eventId, ?string $credential, bool $throwWhenMissing = true): ?Voter
    {
        if (! $credential) {
            if ($throwWhenMissing) {
                throw new DomainException('ACCESS_CREDENTIAL_REQUIRED', 'Este evento requiere tu código personal o identificador.', 401);
            }

            return null;
        }

        return Voter::query()->where('event_id', $eventId)->whereNotNull('code_hash')->get()
            ->first(fn (Voter $voter) => Domain::verifySecret($credential, $voter->code_hash));
    }

    private function findByExternalId(string $eventId, ?string $credential, bool $throwWhenMissing = true): ?Voter
    {
        if (! $credential) {
            if ($throwWhenMissing) {
                throw new DomainException('ACCESS_CREDENTIAL_REQUIRED', 'Este evento requiere tu código personal o identificador.', 401);
            }

            return null;
        }

        return Voter::query()->where('event_id', $eventId)->whereRaw('UPPER(external_id) = ?', [strtoupper(trim($credential))])->first();
    }
}
