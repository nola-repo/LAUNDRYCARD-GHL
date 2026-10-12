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

    public function test_mapping_change_requires_explicit_confirmation(): void
    {
        $s = $this->store();
        $c = $this->connection('LOC_A');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.mappings.assign'), ['laundrycard_store_id' => $s->id, 'ghl_connection_id' => $c->id])->assertSessionHasErrors('confirm');
        $this->assertSame(0, BranchMapping::count());

        $this->post(route('admin.mappings.assign'), ['laundrycard_store_id' => $s->id, 'ghl_connection_id' => $c->id, 'confirm' => 1])->assertSessionHas('status');
        $m = BranchMapping::first();
        $this->assertSame('proposed', $m->status);
        $this->post(route('admin.mappings.verify', $m))->assertSessionHasErrors('confirm');
        $this->assertSame('proposed', $m->fresh()->status);
        $this->post(route('admin.mappings.verify', $m), ['confirm' => 1]);
        $m->refresh();
        $this->assertSame('verified', $m->status);
        $this->assertSame($admin->id, $m->verified_by);
        $this->assertDatabaseHas('mapping_audit_logs', ['branch_mapping_id' => $m->id, 'action' => 'verified', 'user_id' => $admin->id]);
    }

    public function test_one_location_cannot_serve_two_stores_and_disable_keeps_history(): void
    {
        $svc = app(BranchMappingService::class);
        $s1 = $this->store('0160');
        $s2 = $this->store('0679', 'Laundry Time');
        $c = $this->connection('LOC_A');
        $svc->verify($svc->assign($s1, $c, null), null);

        $this->expectException(\DomainException::class);
        try {
            $svc->assign($s2, $c, null);
        } finally {
            $m = BranchMapping::where('laundrycard_store_id', $s1->id)->first();
            $svc->disable($m, null);
            $this->assertSame('disabled', $m->fresh()->status);
            $this->assertNull($m->fresh()->active_store_key);
            $this->assertDatabaseCount('mapping_audit_logs', 3);
            // the location may now be re-assigned elsewhere
            $svc->assign($s2, $c, null);
        }
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

    public function test_suggestions_are_only_suggestions(): void
    {
        $svc = app(BranchMappingService::class);
        $store = $this->store('0160', 'The Laundry Room');
        $this->connection('LOC_A', 'Laundry Room of Lodi');
        $this->connection('LOC_B', 'Sonic Suds Laundromat of Newark');
        $s = $svc->suggest($store);
        $this->assertSame('LOC_A', $s['connection']->location_id);
        $this->assertSame(0, BranchMapping::count());
        $this->assertFalse($svc->resolve($store)['routable']);
    }

    public function test_routing_preview_makes_no_external_calls(): void
    {
        $this->httpFake();
        $this->verifiedMapping('0160', 'LOC_A');
        $this->actingAs($this->admin())->post(route('admin.dev.routing-preview'), ['store_id' => '160'])
            ->assertSessionHas('result', fn ($r) => $r['data']['destination_location_id'] === 'LOC_A' && $r['data']['routable'] === true);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
