<?php

namespace App\Console\Commands;

use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\ContactMapping;
use App\Models\BranchMapping;
use App\Models\SyncRun;
use App\Models\DailyMetric;
use Illuminate\Console\Command;
use Carbon\CarbonImmutable;

class GenerateDailyMetrics extends Command
{
    protected $signature = 'integration:daily-metrics';
    protected $description = 'Collect daily integration statistics and store them for trend analysis.';

    public function handle(): int
    {
        $date = CarbonImmutable::today();
        $total = Rec::count();
        $processed = Rec::whereIn('processing_status', [Rec::SYNCED_CREATED, Rec::SYNCED_UPDATED])->count();
        $created = ContactMapping::where('action', 'created')->count();
        $updated = ContactMapping::where('action', 'updated')->count();
        $pending = Rec::where('processing_status', Rec::PENDING)->count();
        $failed = Rec::where('processing_status', Rec::FAILED)->count();
        $lastDiscovery = SyncRun::where('type', 'discovery')
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first()?->finished_at;
        $lastSync = BranchMapping::max('last_synced_at');

        DailyMetric::updateOrCreate(
            ['run_date' => $date->toDateString()],
            [
                'total_records' => $total,
                'processed_records' => $processed,
                'contacts_created' => $created,
                'contacts_updated' => $updated,
                'pending_sync' => $pending,
                'failed_sync' => $failed,
                'last_discovery_at' => $lastDiscovery,
                'last_sync_at' => $lastSync,
            ]
        );

        $this->info('Daily metrics recorded for '.$date->toDateString());
        return 0;
    }
}

?>