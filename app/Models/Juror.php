<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Juror extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['individual_weight' => 'decimal:4', 'locked_until' => 'datetime', 'expires_at' => 'datetime', 'last_access_at' => 'datetime', 'created_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(VotingEvent::class, 'event_id');
    }
}
