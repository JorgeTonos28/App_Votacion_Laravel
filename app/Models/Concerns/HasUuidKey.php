<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasUuidKey
{
    use HasUuids;

    public function initializeHasUuidKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
