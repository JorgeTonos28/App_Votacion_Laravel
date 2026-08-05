<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'expires_at' => 'datetime', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(VotingEvent::class, 'event_id');
    }
}
