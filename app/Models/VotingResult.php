<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class VotingResult extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $table = 'results';

    protected $guarded = [];

    protected $casts = ['score' => 'decimal:4', 'quorum_met' => 'boolean', 'calculated_at' => 'datetime', 'validated_at' => 'datetime', 'published_at' => 'datetime'];
}
