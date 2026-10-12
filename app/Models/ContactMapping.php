<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMapping extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(LaundrycardSourceRecord::class, 'source_record_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GhlConnection::class, 'ghl_connection_id');
    }
}
