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
            $table->timestamp('final_cost_submitted_at')->nullable()->after('final_total');
            $table->timestamp('reminded_day_before_at')->nullable()->after('response_deadline');
            $table->timestamp('reminded_hour_before_at')->nullable()->after('reminded_day_before_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['final_cost_submitted_at', 'reminded_day_before_at', 'reminded_hour_before_at']);
        });
    }
};
