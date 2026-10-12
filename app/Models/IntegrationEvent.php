<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
