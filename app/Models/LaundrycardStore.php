<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundrycardStore extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'live_enabled' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'baseline_completed_at' => 'datetime',
            'baseline_started_at' => 'datetime',
            'last_scan_at' => 'datetime',
            'last_successful_scan_at' => 'datetime',
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

    public function sourceRecords(): HasMany
    {
        return $this->hasMany(LaundrycardSourceRecord::class);
    }
}
