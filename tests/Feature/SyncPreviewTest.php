<?php

namespace Tests\Feature;

use App\Models\BranchMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Services\SyncPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class SyncPreviewTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        Cache::flush();
    }

    public function test_preflight_reports_all_prerequisites(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $store = LaundrycardStore::where('store_id', '0160')->first();

        Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'CARD_PRE_001',
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '20.00',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'customer_email'       => 'test@example.com',
            'email_hash'           => \App\Support\Normalizer::hash('test@example.com'),
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $svc = app(SyncPreviewService::class);
        $check = $svc->preflightCheck();

        $this->assertArrayHasKey('sync_enabled', $check);
        $this->assertArrayHasKey('prerequisites', $check);
        $this->assertArrayHasKey('eligible_count', $check);
        $this->assertArrayHasKey('identity_gaps', $check);
        $this->assertArrayHasKey('branch_routing', $check);
        $this->assertArrayHasKey('custom_field_config', $check);
        $this->assertArrayHasKey('sample_records', $check);

        $this->assertSame(1, $check['eligible_count']);
        $this->assertSame(1, $check['identity_gaps']['sendable']);
        $this->assertArrayHasKey('0160', $check['branch_routing']);
        $this->assertTrue($check['branch_routing']['0160']['routable']);
    }

    public function test_dry_run_predicts_actions_without_syncing(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $store = LaundrycardStore::where('store_id', '0160')->first();

        // 1 record with email (sendable)
        Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'CARD_SENDABLE',
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '20.00',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'customer_email'       => 'sendable@example.com',
            'email_hash'           => \App\Support\Normalizer::hash('sendable@example.com'),
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        // 1 anonymous record without email or phone
        Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'CARD_ANON',
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '10.00',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $svc = app(SyncPreviewService::class);
        $dryRun = $svc->dryRun(10);

        $this->assertSame('dry_run', $dryRun['mode']);
        $this->assertSame(2, $dryRun['count']);

        $sendable = collect($dryRun['records'])->firstWhere('card_number', 'CARD_SENDABLE');
        $anon     = collect($dryRun['records'])->firstWhere('card_number', 'CARD_ANON');

        $this->assertSame('create_or_update', $sendable['would_action']);
        $this->assertSame('skip:no_identifier', $anon['would_action']);
        $this->assertContains('store-0160', $sendable['predicted_tags']);
        $this->assertContains('balance-positive', $sendable['predicted_tags']);
    }

    public function test_developer_preview_endpoints_require_admin(): void
    {
        $this->post(route('admin.developer.sync-preview'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.developer.sync-dry-run'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.developer.config-diagnostics'))
            ->assertRedirect(route('login'));
    }

    public function test_developer_preview_endpoints_work_for_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.developer.sync-preview'))
            ->assertSessionHas('result');

        $this->actingAs($admin)
            ->post(route('admin.developer.sync-dry-run'), ['limit' => 5])
            ->assertSessionHas('result');

        $this->actingAs($admin)
            ->post(route('admin.developer.config-diagnostics'))
            ->assertSessionHas('result');
    }
}

