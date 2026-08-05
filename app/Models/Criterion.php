<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Criterion extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['scale_min' => 'decimal:4', 'scale_max' => 'decimal:4', 'weight' => 'decimal:6', 'required' => 'boolean', 'enabled' => 'boolean'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(VotingGroup::class, 'voting_group_id');
    }
}
