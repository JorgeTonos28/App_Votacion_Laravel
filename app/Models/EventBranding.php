<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class EventBranding extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];
}
