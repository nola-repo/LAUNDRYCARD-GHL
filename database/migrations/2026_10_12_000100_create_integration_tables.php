<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('ghl_connections', function (Blueprint $table) {
            $table->id();
            $table->string('location_id')->unique();
            $table->string('company_id')->nullable()->index();
            $table->string('ghl_user_id')->nullable();
            $table->string('user_type', 20)->default('Location');
            $table->string('location_name')->nullable();
            // active | expired | revoked | disconnected | error
            $table->string('status', 20)->default('active')->index();
            $table->text('access_token')->nullable();   // encrypted cast
            $table->text('refresh_token')->nullable();  // encrypted cast
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();       // non-sensitive installation metadata
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_reinstalled_at')->nullable();
            $table->unsignedInteger('install_count')->default(1);
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('laundrycard_stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_id', 20)->unique(); // zero padded, e.g. 0160
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 20)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('live_enabled')->nullable();
            $table->string('status', 20)->default('active'); // active | missing
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('baseline_completed_at')->nullable();
            $table->timestamp('baseline_started_at')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamp('last_successful_scan_at')->nullable();
            $table->string('last_scan_status', 20)->nullable();
            $table->text('last_scan_error')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundrycard_store_id')->constrained('laundrycard_stores')->restrictOnDelete();
            $table->foreignId('ghl_connection_id')->constrained('ghl_connections')->restrictOnDelete();
            // proposed | verified | disabled
            $table->string('status', 20)->default('proposed')->index();
            $table->string('origin', 20)->default('manual'); // manual | suggested
            $table->unsignedTinyInteger('suggestion_score')->nullable();
            // Uniqueness of ACTIVE (verified) mappings. NULL for non verified rows,
            // so any number of historical / proposed rows can coexist.
            $table->unsignedBigInteger('active_store_key')->nullable()->unique();
            $table->unsignedBigInteger('active_connection_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['laundrycard_store_id', 'status']);
            $table->index(['ghl_connection_id', 'status']);
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->string('type', 30);   // discovery | store_refresh | sync
            $table->string('mode', 20)->default('live'); // live | read_only
            $table->string('status', 20)->default('running'); // running | completed | failed | partial
            $table->string('trigger', 20)->default('schedule');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'finished_at']);
        });

        Schema::create('laundrycard_source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundrycard_store_id')->constrained('laundrycard_stores')->restrictOnDelete();
            $table->string('card_number', 40);
            $table->timestamp('source_timestamp')->nullable();
            $table->timestamp('first_observed_at');
            $table->timestamp('last_observed_at');
            // baseline | new_observed | confirmed_new
            $table->string('classification', 20)->index();
            // baseline_skipped | pending | queued | processing | synced_created |
            // synced_updated | held | skipped | failed
            $table->string('processing_status', 20)->index();
            $table->string('hold_reason', 60)->nullable();
            // Customer data is encrypted at rest; hashes support lookups without plaintext.
            $table->text('customer_name')->nullable();
            $table->text('customer_email')->nullable();
            $table->text('customer_phone')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->foreignId('ghl_connection_id')->nullable()->constrained('ghl_connections')->nullOnDelete();
            $table->string('destination_location_id')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['laundrycard_store_id', 'card_number']);
            $table->index(['processing_status', 'first_observed_at']);
        });

        Schema::create('contact_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_record_id')->unique()->constrained('laundrycard_source_records')->restrictOnDelete();
            $table->foreignId('ghl_connection_id')->constrained('ghl_connections')->restrictOnDelete();
            $table->string('location_id')->index();
            $table->string('ghl_contact_id');
            $table->string('action', 20); // created | updated | linked
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'ghl_contact_id']);
        });

        Schema::create('sync_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->foreignId('source_record_id')->constrained('laundrycard_source_records')->cascadeOnDelete();
            $table->uuid('correlation_id')->index();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            // success | held | failed | skipped | dry_run
            $table->string('status', 20)->index();
            $table->string('stage', 40)->nullable();
            $table->string('outcome', 40)->nullable();
            $table->foreignId('ghl_connection_id')->nullable()->constrained('ghl_connections')->nullOnDelete();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable(); // sanitized
            $table->boolean('retry_eligible')->default(false);
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->string('stage', 60)->index();
            $table->string('level', 10)->default('info');
            $table->string('outcome', 30)->nullable()->index();
            $table->string('store_id', 20)->nullable()->index();
            $table->unsignedBigInteger('source_record_id')->nullable()->index();
            $table->string('ghl_location_id')->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('message')->nullable();
            $table->json('context')->nullable(); // sanitized
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('mapping_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_mapping_id')->nullable()->constrained('branch_mappings')->nullOnDelete();
            $table->foreignId('laundrycard_store_id')->nullable()->constrained('laundrycard_stores')->nullOnDelete();
            $table->foreignId('ghl_connection_id')->nullable()->constrained('ghl_connections')->nullOnDelete();
            $table->string('action', 30); // proposed | assigned | verified | disabled | changed
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_audit_logs');
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('sync_attempts');
        Schema::dropIfExists('contact_mappings');
        Schema::dropIfExists('laundrycard_source_records');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('branch_mappings');
        Schema::dropIfExists('laundrycard_stores');
        Schema::dropIfExists('ghl_connections');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
