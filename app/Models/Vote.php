<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vote extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['raw_score' => 'decimal:4', 'normalized_score' => 'decimal:4', 'submitted_at' => 'datetime', 'updated_at' => 'datetime', 'invalidated_at' => 'datetime'];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VotingGroup::class, 'voting_group_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(VoteDetail::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(VoteRevision::class);
    }
}
