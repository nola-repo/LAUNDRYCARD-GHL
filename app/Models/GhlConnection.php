<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhlConnection extends Model
{
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const REVOKED = 'revoked';
    public const DISCONNECTED = 'disconnected';
    public const ERROR = 'error';

    protected $guarded = [];

    /** Tokens never leave the model in serialized form. */
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'metadata' => 'array',
            'installed_at' => 'datetime',
            'last_reinstalled_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(BranchMapping::class);
    }

    public function activeMapping()
    {
        return $this->hasOne(BranchMapping::class)->where('status', BranchMapping::VERIFIED);
    }

    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE && ! empty($this->refresh_token);
    }

    public function tokenExpiryState(): string
    {
        if (! $this->expires_at) {
            return 'unknown';
        }
        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        return $this->expires_at->diffInMinutes(now()) < 5 ? 'expiring' : 'valid';
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
