<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remembers that the periodic service reminder for this visit was already sent.
     */
    public function up(): void
    {
        Schema::table('unit_service_logs', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('next_recommended_service_date');
        });
    }

    public function down(): void
    {
        Schema::table('unit_service_logs', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
