<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LaundrycardSourceRecord extends Model
{
    // classification
    public const BASELINE = 'baseline';
    public const NEW_OBSERVED = 'new_observed';
    public const CONFIRMED_NEW = 'confirmed_new';

    // processing status
    public const BASELINE_SKIPPED = 'baseline_skipped';
    public const PENDING = 'pending';
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const SYNCED_CREATED = 'synced_created';
    public const SYNCED_UPDATED = 'synced_updated';
    public const HELD = 'held';
    public const SKIPPED = 'skipped';
    public const FAILED = 'failed';

    protected $guarded = [];

    protected $hidden = ['customer_name', 'customer_email', 'customer_phone'];

    protected function casts(): array
    {
        return [
            'source_timestamp' => 'datetime',
            'first_observed_at' => 'datetime',
            'last_observed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'synced_at' => 'datetime',
            'customer_name' => 'encrypted',
            'customer_email' => 'encrypted',
            'customer_phone' => 'encrypted',
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

    public function contactMapping(): HasOne
    {
        return $this->hasOne(ContactMapping::class, 'source_record_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(SyncAttempt::class, 'source_record_id');
    }

    public function isSynced(): bool
    {
        return in_array($this->processing_status, [self::SYNCED_CREATED, self::SYNCED_UPDATED], true);
    }
}
