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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('gateway_ref');
            $table->string('biller_code', 20)->nullable()->after('va_number');
            $table->string('qr_url')->nullable()->after('qr_string');
            $table->string('deeplink_url')->nullable()->after('qr_url');
            $table->string('gateway_status', 30)->nullable()->after('status');
            $table->timestamp('synced_at')->nullable()->after('paid_at');
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
            $table->dropColumn(['transaction_id', 'biller_code', 'qr_url', 'deeplink_url', 'gateway_status', 'synced_at']);
        });
    }
};
