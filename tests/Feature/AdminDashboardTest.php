<?php

namespace Tests\Feature;

use App\Models\BranchMapping;
use App\Models\User;
use App\Services\BranchMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
    }

    public function test_guests_are_redirected_and_non_admins_forbidden(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_non_admin_cannot_log_in(): void
    {
        $u = User::factory()->create(['password' => 'correct-horse-battery']);
        $this->post('/login', ['email' => $u->email, 'password' => 'correct-horse-battery'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_every_dashboard_section_renders_for_admin(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $this->store('0679', 'Laundry Time');
        $a = $this->actingAs($this->admin());
        foreach (['overview', 'installations', 'mappings', 'records', 'transactions', 'logs', 'failed', 'developer', 'oauth.diagnose'] as $r) {
            $a->get(route("admin.$r"))->assertOk();
        }

        // Detailed overview assertions
        $overviewHtml = $a->get(route('admin.overview'))->getContent();
        $this->assertStringContainsString('sec-hero', $overviewHtml);
        $this->assertStringContainsString('sec-health', $overviewHtml);
        $this->assertStringContainsString('sec-kpis', $overviewHtml);
        $this->assertStringContainsString('sec-pipeline', $overviewHtml);
        $this->assertStringContainsString('sec-branches', $overviewHtml);
        $this->assertStringContainsString('sec-customers', $overviewHtml);
        $this->assertStringContainsString('sec-activity', $overviewHtml);
        $this->assertStringContainsString('dashboard-live-state', $overviewHtml);
        $this->assertStringContainsString('dashboard-new-customers', $overviewHtml);
        $this->assertStringContainsString('dashboard-events', $overviewHtml);
        $this->assertStringContainsString('data-stat="records"', $overviewHtml);
        $this->assertStringContainsString('data-stat="new_customers"', $overviewHtml);
        $this->assertStringContainsString('data-stat="branches"', $overviewHtml);
        $this->assertStringContainsString('data-stat="locations"', $overviewHtml);
    }

    public function test_dashboard_never_renders_tokens_or_secrets(): void
    {
        $this->connection('LOC_A');
        $html = $this->actingAs($this->admin())->get(route('admin.installations'))->getContent();
        $this->assertStringNotContainsString('ACCESS_LOC_A', $html);
        $this->assertStringNotContainsString('REFRESH_LOC_A', $html);
        $this->assertStringNotContainsString('client-secret-SHOULD-NEVER-LEAK', $html);
        $dev = $this->actingAs($this->admin())->get(route('admin.developer'))->getContent();
        $this->assertStringNotContainsString('super-secret-lc-password', $dev);
    }

    public function test_admin_pages_are_monitoring_only(): void
    {
        $this->store();
        $this->connection('LOC_A');
        $admin = $this->actingAs($this->admin());
        foreach (['mappings', 'installations', 'failed', 'developer'] as $page) {
            $html = $admin->get(route("admin.$page"))->assertOk()->getContent();
            foreach (['Assign branch', 'Propose', 'Approve', 'Verify', 'Disconnect', 'Retry', 'Process now', 'Preview decision'] as $control) {
                $this->assertStringNotContainsString($control, $html);
            }
        }
        $admin->post('/admin/mappings/assign')->assertNotFound();
        $this->assertSame(0, BranchMapping::count());
    }

    public function test_one_location_cannot_be_active_for_two_stores(): void
    {
        $s1 = $this->store('0160');
        $s2 = $this->store('0679', 'Laundry Time');
        $c = $this->connection('LOC_A');
        $this->verifiedMapping('0160', 'LOC_A', $s1, $c);

        $this->expectException(\Illuminate\Database\QueryException::class);
        BranchMapping::create(['laundrycard_store_id' => $s2->id, 'ghl_connection_id' => $c->id, 'status' => 'verified', 'active_store_key' => $s2->id, 'active_connection_key' => $c->id]);
    }

    public function test_database_enforces_unique_active_mapping(): void
    {
        $s = $this->store();
        $c1 = $this->connection('LOC_A');
        $c2 = $this->connection('LOC_B', 'B');
        BranchMapping::create(['laundrycard_store_id' => $s->id, 'ghl_connection_id' => $c1->id, 'status' => 'verified', 'active_store_key' => $s->id, 'active_connection_key' => $c1->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        BranchMapping::create(['laundrycard_store_id' => $s->id, 'ghl_connection_id' => $c2->id, 'status' => 'verified', 'active_store_key' => $s->id, 'active_connection_key' => $c2->id]);
    }

    public function test_exact_configured_pair_is_mapped_only_after_oauth_registration(): void
    {
        $svc = app(BranchMappingService::class);
        $store = $this->store('0160', 'The Laundry Room');
        config(['integration.branch_location_map' => ['0160' => 'LOC_A']]);
        $this->assertSame(0, $svc->autoMapAll());
        $this->assertSame(0, BranchMapping::count());
        $this->assertFalse($svc->resolve($store)['routable']);
        $this->connection('LOC_A', 'Completely Different Display Name');
        $this->assertSame(1, $svc->autoMapAll());
        $this->assertTrue($svc->resolve($store)['routable']);
    }

    public function test_dashboard_polling_endpoint_requires_admin_and_returns_persisted_state(): void
    {
        $this->httpFake();
        $this->verifiedMapping('0160', 'LOC_A');
        $this->get(route('admin.monitoring.updates'))->assertRedirect('/login');
        $response = $this->actingAs($this->admin())->get(route('admin.monitoring.updates'))->assertOk();
        $this->assertSame(4, $response->json('poll_interval_seconds'));
        $this->assertSame('connected', $response->json('connection_state'));
        $this->assertSame(1, $response->json('stats.mappings_verified'));
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_cron_endpoint_fails_closed_without_the_bearer_secret(): void
    {
        $this->httpFake($this->lcFake([], []));
        config(['integration.cron_secret' => 'valid_secret_123']);
        $this->get('/api/cron/discovery')->assertUnauthorized();
        $this->get('/api/cron/discovery', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->get('/api/cron/discovery?secret=wrong')->assertUnauthorized();

        $this->get('/api/cron/discovery', ['Authorization' => 'Bearer valid_secret_123'])->assertOk();
        $this->get('/api/cron/discovery?secret=valid_secret_123')->assertOk();
    }

    public function test_records_csv_export_streams_filtered_data(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $store = \App\Models\LaundrycardStore::where('store_id', '0160')->first();

        \App\Models\LaundrycardSourceRecord::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'EXP_CARD_100',
            'classification'       => \App\Models\LaundrycardSourceRecord::NEW_OBSERVED,
            'processing_status'    => \App\Models\LaundrycardSourceRecord::PENDING,
            'balance'              => '25.00',
            'eligibility_status'   => \App\Models\LaundrycardSourceRecord::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'customer_name'        => 'John Doe',
            'customer_email'       => 'john@example.test',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $a = $this->actingAs($this->admin());
        $res = $a->get(route('admin.records.export'))->assertOk();

        $this->assertTrue($res->headers->contains('content-type', 'text/csv; charset=UTF-8'));
        $content = $res->streamedContent();
        $this->assertStringContainsString('Card Number', $content);
        $this->assertStringContainsString('EXP_CARD_100', $content);
        $this->assertStringContainsString('John', $content);
        $this->assertStringContainsString('Doe', $content);
    }

    public function test_transactions_csv_export_and_view_display_first_and_last_name(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $store = \App\Models\LaundrycardStore::where('store_id', '0160')->first();

        $rec = \App\Models\LaundrycardSourceRecord::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'TX_CARD_200',
            'classification'       => \App\Models\LaundrycardSourceRecord::NEW_OBSERVED,
            'processing_status'    => \App\Models\LaundrycardSourceRecord::PENDING,
            'balance'              => '50.00',
            'eligibility_status'   => \App\Models\LaundrycardSourceRecord::BALANCE_ELIGIBLE,
            'customer_name'        => 'Alice Smith',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        \App\Models\LaundrycardTransaction::create([
            'laundrycard_store_id' => $store->id,
            'source_record_id'     => $rec->id,
            'card_number'          => 'TX_CARD_200',
            'occurred_at'          => now(),
            'transaction_type'     => 'reload',
            'amount'               => '20.00',
            'balance_after'        => '50.00',
            'raw_payload'          => ['AdditionalInfo' => 'Reload test'],
        ]);

        $a = $this->actingAs($this->admin());

        // 1. View renders First Name and Last Name
        $viewRes = $a->get(route('admin.transactions'))->assertOk();
        $this->assertStringContainsString('First Name', $viewRes->getContent());
        $this->assertStringContainsString('Last Name', $viewRes->getContent());
        $this->assertStringContainsString('Alice', $viewRes->getContent());
        $this->assertStringContainsString('Smith', $viewRes->getContent());

        // 2. CSV export streams transaction record with names
        $csvRes = $a->get(route('admin.transactions.export'))->assertOk();
        $this->assertTrue($csvRes->headers->contains('content-type', 'text/csv; charset=UTF-8'));
        $content = $csvRes->streamedContent();
        $this->assertStringContainsString('TX_CARD_200', $content);
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('Smith', $content);
    }

    public function test_queue_cron_drains_jobs_and_recovers_stuck_processing_records(): void
    {
        config(['integration.cron_secret' => 'valid_secret_123', 'queue.default' => 'database']);
        $this->verifiedMapping('0160', 'LOC_A');
        $store = \App\Models\LaundrycardStore::where('store_id', '0160')->first();
        $base = [
            'laundrycard_store_id' => $store->id, 'classification' => \App\Models\LaundrycardSourceRecord::NEW_OBSERVED,
            'balance' => '10.00', 'eligibility_status' => \App\Models\LaundrycardSourceRecord::BALANCE_ELIGIBLE,
            'first_observed_at' => now(), 'last_observed_at' => now(),
        ];
        $stuck = \App\Models\LaundrycardSourceRecord::create($base + ['card_number' => 'STUCK_1', 'processing_status' => 'processing', 'last_attempt_at' => now()->subHour(), 'customer_name' => 'Stuck Person']);
        $anon = \App\Models\LaundrycardSourceRecord::create($base + ['card_number' => 'ANON_1', 'processing_status' => 'queued']);
        \App\Jobs\SyncSourceRecordJob::dispatch($anon->id, 'corr-test');

        $this->httpFake($this->ghlFake());
        $res = $this->get('/api/cron/queue', ['Authorization' => 'Bearer valid_secret_123'])->assertOk();

        $this->assertSame(1, $res->json('recovered'));
        $this->assertSame(1, $res->json('processed'));
        $this->assertSame(0, $res->json('backlog'));
        $this->assertSame('pending', $stuck->fresh()->processing_status);
        $this->assertSame('skipped', $anon->fresh()->processing_status);
        $this->assertSame('no_contact_identifier', $anon->fresh()->hold_reason);
        $this->assertCount(0, $this->ghlSent('/contacts'));
    }

    public function test_admin_can_refresh_single_and_batch_oauth_tokens(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $conn = \App\Models\GhlConnection::where('location_id', 'LOC_A')->first();

        $this->httpFake([
            'services.leadconnectorhq.com/oauth/token' => \Illuminate\Support\Facades\Http::response([
                'access_token'  => 'new_refreshed_access_token',
                'refresh_token' => 'new_refreshed_refresh_token',
                'expires_in'    => 86400,
                'scope'         => 'contacts.readonly contacts.write locations.readonly',
            ]),
        ]);

        $a = $this->actingAs($this->admin());

        // Refresh single
        $a->post(route('admin.connections.refresh', $conn))->assertRedirect();
        $this->assertNotNull($conn->fresh()->last_refreshed_at);

        // Refresh batch
        $a->post(route('admin.connections.refresh-all'))->assertRedirect();
    }
}
