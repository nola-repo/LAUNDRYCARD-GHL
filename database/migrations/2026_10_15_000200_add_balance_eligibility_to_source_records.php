<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laundrycard_source_records', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->nullable()->after('source_timestamp');
            $table->string('eligibility_status', 40)->default('pending_balance_verification')->index()->after('classification');
            $table->string('eligibility_reason', 80)->nullable()->after('eligibility_status');
        });
    }

    public function down(): void
    {
        Schema::table('laundrycard_source_records', function (Blueprint $table) {
            $table->dropColumn(['balance', 'eligibility_status', 'eligibility_reason']);
        });
    }
};
