<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: LaundryCard Transaction Ingestion
 *
 * Stores raw transaction payloads from GET /api/transact.
 * Actual field names and type codes are confirmed at runtime via the
 * raw_payload column — no assumptions are encoded into the schema.
 *
 * The `transaction_ref` column is a stable dedup key composed from
 * the fields that together uniquely identify a transaction. Its exact
 * construction is defined by TransactionDiscoveryService once field
 * names are confirmed from a live API response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundrycard_transactions', function (Blueprint $table) {
            $table->id();

            // Store association
            $table->foreignId('laundrycard_store_id')
                ->constrained('laundrycard_stores')
                ->cascadeOnDelete();

            // Card association (nullable — card may not be discovered yet)
            $table->foreignId('source_record_id')
                ->nullable()
                ->constrained('laundrycard_source_records')
                ->nullOnDelete();

            $table->string('card_number', 40)->index();

            // Timing
            $table->timestamp('occurred_at')->index();

            // Transaction classification — set only after type codes are confirmed.
            // Raw value from API; never assumed.
            $table->string('transaction_type_raw', 80)->nullable();

            // Normalised type once confirmed: 'reload', 'vend', 'bonus', 'adjustment', 'unknown'
            $table->string('transaction_type', 40)->nullable()->default(null);

            // Financial fields — nullable until field names are confirmed
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('bonus_amount', 12, 2)->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();

            // POS terminal identifier from the response (may differ from MachNo used for Account calls)
            $table->string('mach_no', 20)->nullable();

            // Full original API payload preserved for re-processing / field-name discovery
            $table->jsonb('raw_payload');

            // Dedup key — stable identifier for the transaction (populated after field confirmation)
            $table->string('transaction_ref', 128)->nullable()->unique();

            $table->timestamp('ingested_at')->useCurrent();
            $table->timestamps();
        });

        // Composite covering indexes for common query patterns
        Schema::table('laundrycard_transactions', function (Blueprint $table) {
            // Per-store chronological scan
            $table->index(['laundrycard_store_id', 'occurred_at'], 'tx_store_time');
            // Per-card activity lookup
            $table->index(['card_number', 'transaction_type'], 'tx_card_type');
            // Record association
            $table->index(['source_record_id', 'occurred_at'], 'tx_record_time');
        });

        // Per-store transaction cursor (independent of the card-discovery cursor)
        Schema::table('laundrycard_stores', function (Blueprint $table) {
            $table->timestamp('last_transaction_cursor_at')->nullable()->after('last_sync_card_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('laundrycard_stores', function (Blueprint $table) {
            $table->dropColumnIfExists('last_transaction_cursor_at');
        });
        Schema::dropIfExists('laundrycard_transactions');
    }
};

