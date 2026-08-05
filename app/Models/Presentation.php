<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presentation extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['stage_started_at' => 'datetime', 'stage_ended_at' => 'datetime', 'voting_opened_at' => 'datetime', 'voting_closed_at' => 'datetime', 'timer_paused_at' => 'datetime', 'published_at' => 'datetime'];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(VotingEvent::class, 'event_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
