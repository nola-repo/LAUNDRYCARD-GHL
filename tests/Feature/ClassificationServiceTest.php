<?php

namespace Tests\Feature;

use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardTransaction as Tx;
use App\Services\ContactClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class ClassificationServiceTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        config([
            'integration.classification.balance_low_max'  => 10.00,
            'integration.classification.balance_high_min' => 25.00,
        ]);
    }

    private function record(string $storeId = '0160', float $balance = 15.00, string $eligibility = Rec::BALANCE_ELIGIBLE): Rec
    {
        $store = $this->store($storeId);

        return Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'CARD-' . uniqid(),
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => (string) $balance,
            'eligibility_status'   => $eligibility,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);
    }

    // ------------------------------------------------------------------ source tag

    public function test_laundrycard_source_tag_always_present(): void
    {
        $record = $this->record();
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('laundrycard', $tags);
    }

    // ------------------------------------------------------------------ branch tags

    public function test_branch_tag_uses_store_id(): void
    {
        $record = $this->record('0160');
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('store-0160', $tags);
        $this->assertNotContains('store-0679', $tags);
    }

    public function test_different_stores_produce_different_branch_tags(): void
    {
        $svc = app(ContactClassificationService::class);

        $r160 = $this->record('0160');
        $r679 = $this->record('0679');

        $this->assertContains('store-0160', $svc->managedTags($r160));
        $this->assertContains('store-0679', $svc->managedTags($r679));
    }

    // ------------------------------------------------------------------ balance status

    public function test_balance_positive_tag_when_eligible(): void
    {
        $record = $this->record('0160', 15.00, Rec::BALANCE_ELIGIBLE);
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('balance-positive', $tags);
        $this->assertNotContains('balance-zero', $tags);
        $this->assertNotContains('balance-negative', $tags);
    }

    public function test_balance_zero_tag(): void
    {
        $record = $this->record('0160', 0.0, Rec::BALANCE_ZERO);
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('balance-zero', $tags);
        $this->assertNotContains('balance-positive', $tags);
    }

    public function test_balance_negative_tag(): void
    {
        $record = $this->record('0160', -1.00, Rec::BALANCE_NEGATIVE);
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('balance-negative', $tags);
    }

    // ------------------------------------------------------------------ balance tiers

    public function test_balance_low_tag_applied_below_threshold(): void
    {
        $record = $this->record('0160', 5.00, Rec::BALANCE_ELIGIBLE);
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('balance-low', $tags);
        $this->assertNotContains('balance-high', $tags);
    }

    public function test_balance_high_tag_applied_at_or_above_threshold(): void
    {
        $record = $this->record('0160', 30.00, Rec::BALANCE_ELIGIBLE);
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertContains('balance-high', $tags);
        $this->assertNotContains('balance-low', $tags);
    }

    public function test_no_tier_tag_for_mid_range_balance(): void
    {
        $record = $this->record('0160', 15.00, Rec::BALANCE_ELIGIBLE); // between 10 and 25
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        $this->assertNotContains('balance-low', $tags);
        $this->assertNotContains('balance-high', $tags);
    }

    public function test_configurable_tier_thresholds(): void
    {
        config([
            'integration.classification.balance_low_max'  => 20.00,
            'integration.classification.balance_high_min' => 50.00,
        ]);

        $record = $this->record('0160', 15.00, Rec::BALANCE_ELIGIBLE);
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        // 15 < 20 → now low
        $this->assertContains('balance-low', $tags);
    }

    // ------------------------------------------------------------------ tag merging and preservation

    public function test_human_assigned_tags_are_preserved(): void
    {
        $record = $this->record('0160');
        $svc    = app(ContactClassificationService::class);

        $existing = ['vip-customer', 'do-not-call', 'laundrycard live', 'inactive-override', 'laundrycard', 'store-0160'];
        $merged   = $svc->tags($record, $existing);

        $this->assertContains('vip-customer', $merged);
        $this->assertContains('do-not-call', $merged);
        $this->assertContains('laundrycard live', $merged);
        $this->assertContains('inactive-override', $merged);
        $this->assertContains('laundrycard', $merged);
    }

    public function test_no_duplicate_tags_in_merged_set(): void
    {
        $record   = $this->record('0160');
        $svc      = app(ContactClassificationService::class);
        $existing = ['laundrycard', 'store-0160', 'balance-positive'];

        $merged   = $svc->tags($record, $existing);
        $this->assertSame(array_unique($merged), $merged);
    }

    public function test_tags_are_deterministic(): void
    {
        $record = $this->record('0160', 15.00, Rec::BALANCE_ELIGIBLE);
        $svc    = app(ContactClassificationService::class);

        $first  = $svc->managedTags($record);
        $second = $svc->managedTags($record);
        $this->assertSame($first, $second);
    }

    public function test_tags_are_sorted_for_stability(): void
    {
        $record   = $this->record('0160');
        $svc      = app(ContactClassificationService::class);
        $existing = ['z-custom', 'a-custom'];

        $merged = $svc->tags($record, $existing);
        $sorted = $merged;
        sort($sorted);
        $this->assertSame($sorted, $merged);
    }

    // ------------------------------------------------------------------ transaction tags (gated)

    public function test_transaction_tags_not_applied_when_no_typed_transactions_exist(): void
    {
        $record = $this->record('0160');
        $svc    = app(ContactClassificationService::class);
        $tags   = $svc->managedTags($record);

        // No typed transactions in DB → transaction-based tags must NOT appear
        $this->assertNotContains('reloaded-30d', $tags);
        $this->assertNotContains('active-30d', $tags);
        $this->assertNotContains('inactive', $tags);
    }

    // ------------------------------------------------------------------ custom fields

    public function test_custom_fields_empty_when_no_field_ids_configured(): void
    {
        config(['integration.classification.custom_fields' => []]);

        $record = $this->record('0160');
        $svc    = app(ContactClassificationService::class);

        $this->assertSame([], $svc->customFields($record, 'LOC_A'));
    }

    public function test_custom_fields_populated_when_ids_configured(): void
    {
        config(['integration.classification.custom_fields' => [
            'LOC_A' => [
                'card_number' => 'UUID-CARD-FIELD',
                'balance'     => 'UUID-BALANCE-FIELD',
                'store_id'    => 'UUID-STORE-FIELD',
            ],
        ]]);

        $record = $this->record('0160', 15.00);
        $record->load('store');

        $svc    = app(ContactClassificationService::class);
        $fields = $svc->customFields($record, 'LOC_A');

        $this->assertNotEmpty($fields);
        $ids = array_column($fields, 'id');
        $this->assertContains('UUID-CARD-FIELD', $ids);
        $this->assertContains('UUID-BALANCE-FIELD', $ids);
        $this->assertContains('UUID-STORE-FIELD', $ids);
    }

    public function test_custom_fields_per_location_isolation(): void
    {
        config(['integration.classification.custom_fields' => [
            'LOC_A' => ['card_number' => 'UUID-A'],
            'LOC_B' => ['card_number' => 'UUID-B'],
        ]]);

        $record = $this->record('0160');
        $svc    = app(ContactClassificationService::class);

        $fieldsA = $svc->customFields($record, 'LOC_A');
        $fieldsB = $svc->customFields($record, 'LOC_B');

        $this->assertSame(['UUID-A'], array_column($fieldsA, 'id'));
        $this->assertSame(['UUID-B'], array_column($fieldsB, 'id'));
    }

    // ------------------------------------------------------------------ classify() summary

    public function test_classify_returns_complete_summary(): void
    {
        $record = $this->record('0160', 15.00, Rec::BALANCE_ELIGIBLE);
        $svc    = app(ContactClassificationService::class);
        $result = $svc->classify($record);

        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('managed_tags', $result);
        $this->assertArrayHasKey('custom_fields', $result);
        $this->assertArrayHasKey('tx_fields_confirmed', $result);
        $this->assertArrayHasKey('balance_eligible', $result);
        $this->assertArrayHasKey('has_identity', $result);
        $this->assertTrue($result['balance_eligible']);
        $this->assertFalse($result['tx_fields_confirmed']);
    }
}

