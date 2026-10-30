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
            $table->foreignId('payout_id')->nullable()->after('fund_status')->constrained()->nullOnDelete();
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->string('bank_name', 50)->nullable()->after('amount');
            $table->string('account_name')->nullable()->after('bank_name');
            $table->string('account_number', 30)->nullable()->after('account_name');
            $table->foreignId('created_by')->nullable()->after('reference')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['bank_name', 'account_name', 'account_number']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });
    }
};
