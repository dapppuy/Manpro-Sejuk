<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PROMOSI feature was removed; search order no longer depends on it.
     */
    public function up(): void
    {
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_promoted', 'promoted_until']);
        });
    }

    public function down(): void
    {
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->boolean('is_promoted')->default(false)->after('is_accepting_orders');
            $table->timestamp('promoted_until')->nullable()->after('is_promoted');
        });
    }
};
