<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'retry_eligible' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(LaundrycardSourceRecord::class, 'source_record_id');
    }
}
