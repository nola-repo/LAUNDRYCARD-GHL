<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('run_date')->unique();
            $table->unsignedBigInteger('total_records');
            $table->unsignedBigInteger('processed_records');
            $table->unsignedBigInteger('contacts_created');
            $table->unsignedBigInteger('contacts_updated');
            $table->unsignedBigInteger('pending_sync');
            $table->unsignedBigInteger('failed_sync');
            $table->timestamp('last_discovery_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
    }
};
