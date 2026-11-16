<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('trip_started_at')->nullable()->after('scheduled_window');
            $table->unsignedSmallInteger('trip_eta_minutes')->nullable()->after('trip_started_at');
            $table->timestamp('arrived_at')->nullable()->after('trip_eta_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['trip_started_at', 'trip_eta_minutes', 'arrived_at']);
        });
    }
};
