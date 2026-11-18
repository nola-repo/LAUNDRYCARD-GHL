<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\CarbonImmutable;
use App\Models\DailyMetric;

class DailyMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_metrics_command_creates_row()
    {
        $today = CarbonImmutable::today();

        $this->artisan('integration:daily-metrics')
            ->expectsOutput('Daily metrics recorded for '. $today->toDateString())
            ->assertExitCode(0);

        $this->assertDatabaseHas('daily_metrics', [
            'run_date' => $today->toDateString(),
        ]);
    }
}
