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
        Schema::table('work_proofs', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('note');
            $table->text('complaint')->nullable()->after('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_proofs', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'complaint']);
        });
    }
};
