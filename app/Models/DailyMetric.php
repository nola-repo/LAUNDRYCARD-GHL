<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    protected $table = 'daily_metrics';
    protected $guarded = [];
    protected $dates = ['run_date', 'last_discovery_at', 'last_sync_at'];
}
