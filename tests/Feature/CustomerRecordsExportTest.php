<?php

namespace Tests\Feature;

use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\User;
use App\Support\CustomerRecordsCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class CustomerRecordsExportTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
    }

    public function test_export_matches_combined_filters_across_pages_with_only_selected_columns(): void
    {
        $store = $this->store('0160');
        $otherStore = $this->store('0679');
        $base = [
            'laundrycard_store_id' => $store->id,
            'classification' => Rec::NEW_OBSERVED,
            'processing_status' => Rec::HELD,
            'hold_reason' => 'no_branch_mapping',
            'eligibility_status' => Rec::BALANCE_ELIGIBLE,
            'customer_name' => 'Jamie Chen',
            'customer_email' => 'jamie@example.test',
            'balance' => '12.50',
            'first_observed_at' => '2026-11-10 12:00:00',
            'last_observed_at' => '2026-11-10 12:00:00',
        ];
        for ($i = 0; $i < 18; $i++) {
            Rec::create(array_replace($base, ['card_number' => 'MATCH-'.$i]));
        }
        foreach ([
            ['laundrycard_store_id' => $otherStore->id],
            ['classification' => Rec::BASELINE],
            ['processing_status' => Rec::PENDING],
            ['hold_reason' => 'missing_contact_info'],
            ['customer_name' => null],
            ['first_observed_at' => '2026-10-31 23:59:59'],
            ['first_observed_at' => '2026-12-01 00:00:00'],
            ['card_number' => 'OTHER-CARD'],
        ] as $i => $overrides) {
            Rec::create(array_replace($base, ['card_number' => 'MATCH-DECOY-'.$i], $overrides));
        }
        $filters = [
            'tab' => 'new', 'search' => 'MATCH', 'store' => '0160',
            'classification' => Rec::NEW_OBSERVED, 'status' => Rec::HELD,
            'has_name' => 'yes', 'outcome' => 'no_branch_mapping',
            'from' => '2026-11-01', 'to' => '2026-11-30',
            'page' => 2, 'per_page' => 15,
        ];
        $this->actingAs($this->admin());
        $this->get(route('admin.records', $filters))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 18 && $records->count() === 3);
        $response = $this->get(route('admin.records.export', $filters + [
            'customize' => 1, 'columns' => ['balance', 'card_number'],
        ]))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = preg_split('/\r?\n/', trim(substr($csv, 3)));
        $this->assertCount(19, $lines);
        $this->assertSame(['Card Number', 'Current Balance'], str_getcsv(array_shift($lines)));
        $cards = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $this->assertCount(2, $row);
            $this->assertSame('12.50', $row[1]);
            $cards[] = $row[0];
        }
        $this->assertEqualsCanonicalizing(array_map(fn ($i) => 'MATCH-'.$i, range(0, 17)), $cards);
        $this->assertStringNotContainsString('jamie@example.test', $csv);
    }

    public function test_custom_export_rejects_missing_empty_unknown_and_duplicate_columns(): void
    {
        $this->actingAs($this->admin());
        foreach ([
            ['customize' => 1],
            ['customize' => 1, 'columns' => []],
            ['columns' => 'card_number'],
            ['columns' => ['access_token']],
            ['columns' => ['card_number', 'card_number']],
        ] as $options) {
            $this->getJson(route('admin.records.export', $options))->assertUnprocessable();
        }
    }

    public function test_default_and_empty_exports_keep_the_existing_schema(): void
    {
        $this->actingAs($this->admin());
        $csv = $this->get(route('admin.records.export', ['search' => 'missing']))->assertOk()->streamedContent();
        $this->assertSame(array_values(CustomerRecordsCsv::COLUMNS), str_getcsv(trim(substr($csv, 3))));
        $this->assertEqualsCanonicalizing(array_keys(CustomerRecordsCsv::COLUMNS), array_merge(...array_values(CustomerRecordsCsv::GROUPS)));
    }

    public function test_export_keeps_admin_authorization(): void
    {
        $this->get(route('admin.records.export'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.records.export'))->assertForbidden();
    }
}
