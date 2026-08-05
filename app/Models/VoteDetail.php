<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class VoteDetail extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['raw_value' => 'decimal:4', 'normalized_value' => 'decimal:4', 'criterion_weight' => 'decimal:6', 'weighted_value' => 'decimal:4'];
}
