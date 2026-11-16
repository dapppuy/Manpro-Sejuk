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
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->json('selection_progress')->nullable()->after('verification_note');
            $table->timestamp('agreement_accepted_at')->nullable()->after('selection_progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->dropColumn(['selection_progress', 'agreement_accepted_at']);
        });
    }
};
