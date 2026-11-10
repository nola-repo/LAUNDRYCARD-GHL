<?php

namespace App\Services;

use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardTransaction as Tx;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized source of truth for GHL contact tags and custom field values.
 *
 * Rules:
 * - Tags are deterministic: same input always produces the same output.
 * - Integration-managed tags are explicitly namespaced (source-, store-, balance-).
 * - Existing tags NOT in our managed set are always preserved.
 * - Balance-tier thresholds come from config — never hardcoded business assumptions.
 * - Reload/activity tags are gated behind TRANSACTION_FIELDS_CONFIRMED and are
 *   only applied after actual transaction type codes have been verified.
 * - Custom field IDs are per-location and must be configured before use.
 */
class ContactClassificationService
{
    // ------------------------------------------------------------------ tag namespaces

    /** Exact tags we own and manage. */
    private const MANAGED_EXACT = [
        'laundrycard',   // source tag (exact)
        'inactive',      // inactivity flag (exact)
    ];

    /** Prefixed tag namespaces we own and manage (must end with a hyphen). */
    private const MANAGED_PREFIXES = [
        'store-',        // branch tags
        'balance-',      // balance status and tier tags
        'reloaded-',     // reload activity (transaction-dependent)
        'active-',       // vend activity (transaction-dependent)
    ];

    // ------------------------------------------------------------------ public API

    /**
     * Generate the full tag set for a contact record.
     *
     * @param  array  $existingTags  Tags currently on the GHL contact (preserved if not managed).
     * @return array<string>  Merged, deduplicated, sorted tag list.
     */
    public function tags(Rec $record, array $existingTags = []): array
    {
        $managed = $this->managedTags($record);

        // Preserve any tags the operator manually set that are not in our managed namespace.
        $preserved = array_values(array_filter($existingTags, fn ($t) => ! $this->isManaged($t)));

        // Build final set: preserved + managed, deduplicated and sorted for stability.
        $all = array_values(array_unique(array_merge($preserved, $managed)));
        sort($all);

        return $all;
    }

    /**
     * Generate only the integration-managed tags for this record.
     * Useful for display/preview without needing the existing contact.
     *
     * @return array<string>
     */
    public function managedTags(Rec $record): array
    {
        $tags = [];

        // A. Source — always present
        $tags[] = 'laundrycard';

        // B. Branch — derived from store ID (zero-padded, never assumed)
        if ($record->store && filled($record->store->store_id)) {
            $tags[] = 'store-' . $record->store->store_id;
        }

        // C. Balance status (uses eligibility_status which is calculated by discovery)
        $tags = array_merge($tags, $this->balanceStatusTags($record));

        // D. Balance tier (configurable thresholds)
        $tags = array_merge($tags, $this->balanceTierTags($record));

        // E. Transaction-based tags — only after field names and type codes are confirmed.
        // Gated by the TYPE_MAP in TransactionDiscoveryService being non-empty.
        if ($this->transactionFieldsConfirmed($record)) {
            $tags = array_merge($tags, $this->transactionTags($record));
        }

        return array_values(array_unique($tags));
    }

    /**
     * Build the custom fields payload for a GHL contact update.
     * Returns an array of {id, field_value} pairs for fields that have values.
     * Returns empty array when no field IDs are configured for this location.
     *
     * @param  string  $locationId  GHL location ID (custom field IDs are per-location).
     * @return array<array{id:string,field_value:mixed}>
     */
    public function customFields(Rec $record, string $locationId): array
    {
        $fieldConfig = config('integration.classification.custom_fields', []);
        $fields = $fieldConfig[$locationId] ?? [];

        if (empty($fields)) {
            return [];
        }

        $result = [];

        if (! empty($fields['card_number'])) {
            $result[] = ['id' => $fields['card_number'], 'field_value' => $record->card_number];
        }

        if (! empty($fields['balance']) && $record->balance !== null) {
            $result[] = ['id' => $fields['balance'], 'field_value' => (float) $record->balance];
        }

        if (! empty($fields['store_id']) && $record->store) {
            $result[] = ['id' => $fields['store_id'], 'field_value' => $record->store->store_id];
        }

        if (! empty($fields['eligibility_status'])) {
            $result[] = ['id' => $fields['eligibility_status'], 'field_value' => $record->eligibility_status];
        }

        // Last reload date — only if transaction fields have been confirmed.
        if (! empty($fields['last_reload_date']) && $this->transactionFieldsConfirmed($record)) {
            $lastReload = $this->lastReloadDate($record);
            if ($lastReload !== null) {
                $result[] = ['id' => $fields['last_reload_date'], 'field_value' => $lastReload];
            }
        }

        // Credit-history derived fields — only when transaction data exists and a field ID is configured.
        if ($this->transactionFieldsConfirmed($record)) {
            if (! empty($fields['total_reloaded'])) {
                $result[] = ['id' => $fields['total_reloaded'], 'field_value' => (float) Tx::where('source_record_id', $record->id)
                    ->where('transaction_type', Tx::TYPE_RELOAD)->sum('amount')];
            }
            if (! empty($fields['last_vend_date'])) {
                $lastVend = Tx::where('source_record_id', $record->id)->where('transaction_type', Tx::TYPE_VEND)->max('occurred_at');
                if ($lastVend !== null) {
                    $result[] = ['id' => $fields['last_vend_date'], 'field_value' => date('Y-m-d', strtotime($lastVend))];
                }
            }
        }

        return $result;
    }

    /**
     * Full classification summary for admin display / dry-run preview.
     */
    public function classify(Rec $record, array $existingTags = [], string $locationId = ''): array
    {
        $managed  = $this->managedTags($record);
        $final    = $this->tags($record, $existingTags);
        $cfFields = $locationId ? $this->customFields($record, $locationId) : [];

        return [
            'tags'              => $final,
            'managed_tags'      => $managed,
            'custom_fields'     => $cfFields,
            'tx_fields_confirmed' => $this->transactionFieldsConfirmed($record),
            'balance_eligible'  => $record->eligibility_status === Rec::BALANCE_ELIGIBLE,
            'has_identity'      => filled($record->customer_email) || filled($record->customer_phone),
        ];
    }

    // ------------------------------------------------------------------ internal tag builders

    private function balanceStatusTags(Rec $record): array
    {
        return match ($record->eligibility_status) {
            Rec::BALANCE_ELIGIBLE  => ['balance-positive'],
            Rec::BALANCE_ZERO      => ['balance-zero'],
            Rec::BALANCE_NEGATIVE  => ['balance-negative'],
            default                => [],
        };
    }

    private function balanceTierTags(Rec $record): array
    {
        if ($record->balance === null || $record->eligibility_status !== Rec::BALANCE_ELIGIBLE) {
            return [];
        }

        $balance  = (float) $record->balance;
        $lowMax   = (float) config('integration.classification.balance_low_max', 10.00);
        $highMin  = (float) config('integration.classification.balance_high_min', 25.00);

        if ($balance < $lowMax) {
            return ['balance-low'];
        }
        if ($balance >= $highMin) {
            return ['balance-high'];
        }

        return [];
    }

    private function transactionTags(Rec $record): array
    {
        if (! $record->store) {
            return [];
        }

        $tags = [];

        // Last reload within 30 / 90 days
        $lastReload = Tx::where('source_record_id', $record->id)
            ->where('transaction_type', Tx::TYPE_RELOAD)
            ->orderByDesc('occurred_at')
            ->value('occurred_at');

        if ($lastReload) {
            $daysSinceReload = abs(now()->diffInDays($lastReload));
            if ($daysSinceReload <= 30) {
                $tags[] = 'reloaded-30d';
            } elseif ($daysSinceReload <= 90) {
                $tags[] = 'reloaded-90d';
            }
        }

        // Activity (last vend)
        $lastVend = Tx::where('source_record_id', $record->id)
            ->where('transaction_type', Tx::TYPE_VEND)
            ->orderByDesc('occurred_at')
            ->value('occurred_at');

        if ($lastVend) {
            $daysSinceVend = abs(now()->diffInDays($lastVend));
            if ($daysSinceVend <= 30) {
                $tags[] = 'active-30d';
            } elseif ($daysSinceVend > 90) {
                $tags[] = 'inactive';
            }
        }

        return $tags;
    }

    private function lastReloadDate(Rec $record): ?string
    {
        $ts = Tx::where('source_record_id', $record->id)
            ->where('transaction_type', Tx::TYPE_RELOAD)
            ->orderByDesc('occurred_at')
            ->value('occurred_at');

        return $ts ? date('Y-m-d', strtotime($ts)) : null;
    }

    // ------------------------------------------------------------------ helpers

    private function isManaged(string $tag): bool
    {
        if (in_array($tag, self::MANAGED_EXACT, true)) {
            return true;
        }

        foreach (self::MANAGED_PREFIXES as $prefix) {
            if (str_starts_with($tag, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transaction-based tags are only applied after the TYPE_MAP in
     * TransactionDiscoveryService is non-empty (i.e., live API type codes confirmed).
     * We detect this by checking whether any typed transactions exist in the DB.
     */
    private function transactionFieldsConfirmed(Rec $record): bool
    {
        if (! Schema::hasTable('laundrycard_transactions')) {
            return false;
        }

        return Tx::whereNotNull('transaction_type')
            ->where('laundrycard_store_id', $record->laundrycard_store_id)
            ->exists();
    }
}

