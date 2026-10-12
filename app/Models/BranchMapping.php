<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMapping extends Model
{
    public const PROPOSED = 'proposed';
    public const VERIFIED = 'verified';
    public const DISABLED = 'disabled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(LaundrycardStore::class, 'laundrycard_store_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GhlConnection::class, 'ghl_connection_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
