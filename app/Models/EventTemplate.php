<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class EventTemplate extends Model
{
    use HasUuidKey;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'configuration_json' => 'array',
        'branding_json' => 'array',
    ];

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->configuration_json, $key, $default);
    }

    public function presentationMinutes(): int
    {
        return (int) round($this->configValue('presentation_duration_seconds', 300) / 60);
    }

    public function votingMinutes(): int
    {
        return (int) round($this->configValue('voting_duration_seconds', 180) / 60);
    }

    public function juryWeight(): float
    {
        return (float) $this->configValue('jury_weight_percent', 70);
    }

    public function publicWeight(): float
    {
        return (float) $this->configValue('public_weight_percent', 30);
    }

    public function criteria(): array
    {
        $criteria = $this->configValue('criteria', []);
        return is_array($criteria) ? $criteria : [];
    }

    public function criteriaCount(): int
    {
        return count($this->criteria());
    }
}
