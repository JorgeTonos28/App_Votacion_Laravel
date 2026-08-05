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

    public function event(): BelongsTo
    {
        return $this->belongsTo(VotingEvent::class, 'event_id');
    }

    public function presentation(): HasOne
    {
        return $this->hasOne(Presentation::class);
    }
}
