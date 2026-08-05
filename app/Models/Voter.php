<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class Voter extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'last_access_at' => 'datetime'];
}
