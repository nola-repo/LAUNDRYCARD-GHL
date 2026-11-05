<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laundrycard_stores', function (Blueprint $table) {
            $table->timestamp('last_sync_cursor_at')->nullable()->after('last_successful_scan_at');
            $table->string('last_sync_card_cursor', 40)->nullable()->after('last_sync_cursor_at');
        });
    }

    public function down(): void
    {
        Schema::table('laundrycard_stores', function (Blueprint $table) {
            $table->dropColumn(['last_sync_cursor_at', 'last_sync_card_cursor']);
        });
    }
};

