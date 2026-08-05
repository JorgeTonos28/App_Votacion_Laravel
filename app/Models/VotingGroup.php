<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VotingGroup extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['weight' => 'decimal:6', 'enabled' => 'boolean', 'minimum_participation_percent' => 'decimal:4', 'require_all_jurors' => 'boolean', 'allow_edit_until_close' => 'boolean', 'show_live_progress' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(VotingEvent::class, 'event_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('sort_order');
    }
}
