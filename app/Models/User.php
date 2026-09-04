<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled', 'failed_attempts', 'locked_until', 'invitation_token', 'invitation_expires_at', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'invitation_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'array',
            'locked_until' => 'datetime',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'Administrator';
    }

    public function isOperator(): bool
    {
        return $this->role === 'Operator';
    }

    public function isAuditor(): bool
    {
        return in_array($this->role, ['Auditor', 'Viewer'], true);
    }
}
