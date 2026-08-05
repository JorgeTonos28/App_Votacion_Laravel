<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VotingEvent extends Model
{
    use HasUuidKey;

    protected $table = 'events';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime',
        'allow_public_vote_edit' => 'boolean', 'allow_juror_vote_edit' => 'boolean',
        'require_quorum_to_publish' => 'boolean',
    ];

    public function branding(): HasOne
    {
        return $this->hasOne(EventBranding::class, 'event_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VotingGroup::class, 'event_id')->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'event_id')->orderBy('presentation_order');
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class, 'event_id')->orderBy('sequence');
    }

    public function jurors(): HasMany
    {
        return $this->hasMany(Juror::class, 'event_id');
    }

    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class, 'event_id');
    }
}
