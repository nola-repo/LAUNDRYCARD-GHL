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
        foreach (['overview', 'installations', 'mappings', 'records', 'logs', 'failed', 'developer'] as $r) {
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
}
