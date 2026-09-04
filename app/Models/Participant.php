<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Participant extends Model
{
    use HasUuidKey;

    protected $guarded = [];

    public function getMemberNamesAttribute(): array
    {
        return self::normalizeMemberNames($this->attributes['members'] ?? null);
    }

    public static function normalizeMemberNames(mixed $members): array
    {
        if (is_string($members)) {
            $members = trim($members);
            if ($members === '') {
                return [];
            }

            $decoded = json_decode($members, true);
            $members = is_array($decoded)
                ? $decoded
                : preg_split('/\s*(?:,|;|\r\n|\r|\n)\s*/u', $members);
        }

        if (! is_array($members)) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($members as $member) {
            if (! is_scalar($member)) {
                continue;
            }

            $name = preg_replace('/\s+/u', ' ', trim((string) $member)) ?? '';
            $key = mb_strtolower($name, 'UTF-8');
            if ($name === '' || isset($seen[$key])) {
                continue;
            }

            $normalized[] = $name;
            $seen[$key] = true;
            if (count($normalized) === 30) {
                break;
            }
        }

        return $normalized;
    }

    public static function serializeMemberNames(mixed $members): ?string
    {
        $normalized = self::normalizeMemberNames($members);

        return $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(VotingEvent::class, 'event_id');
    }

    public function presentation(): HasOne
    {
        return $this->hasOne(Presentation::class)->ofMany('round_number', 'max');
    }
}
