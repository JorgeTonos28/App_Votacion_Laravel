<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['timestamp' => 'datetime'];
}
