<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class EventTemplate extends Model
{
    use HasUuidKey;

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}
