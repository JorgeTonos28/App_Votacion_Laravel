<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Support\Domain;

class AuditService
{
    public function write(
        ?string $eventId,
        string $actorType,
        ?string $actorId,
        string $action,
        string $entityType,
        ?string $entityId,
        mixed $previousValue = null,
        mixed $newValue = null,
        ?string $note = null,
        ?string $requestId = null,
    ): void {
        $request = request();
        AuditEntry::query()->create([
            'event_id' => $eventId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'previous_value_json' => $previousValue === null ? null : json_encode($previousValue, JSON_UNESCAPED_UNICODE),
            'new_value_json' => $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE),
            'request_id' => $requestId ?? ($request?->header('X-Request-Id') ?: Domain::uuid()),
            'note' => $note,
            'technical_fingerprint' => Domain::technicalHash(($request?->ip() ?? '').'|'.($request?->userAgent() ?? '')),
        ]);
    }
}
