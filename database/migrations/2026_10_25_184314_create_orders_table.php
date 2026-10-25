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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('technician_id')->nullable()->constrained('technician_profiles')->nullOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->string('ac_capacity', 10);
            $table->string('ac_brand', 50);
            $table->string('ac_brand_other', 50)->nullable();
            $table->string('address');
            $table->string('address_detail')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('preferred_date');
            $table->string('time_window', 10);
            $table->text('notes')->nullable();
            $table->boolean('warranty')->default(false);
            $table->unsignedInteger('warranty_fee')->default(0);
            $table->unsignedInteger('estimate_min')->nullable();
            $table->unsignedInteger('estimate_max')->nullable();
            $table->text('estimate_note')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_window', 10)->nullable();
            $table->unsignedInteger('final_total')->nullable();
            $table->unsignedInteger('commission_amount')->nullable();
            $table->string('status', 40)->index();
            $table->string('fund_status', 20)->nullable();
            $table->timestamp('response_deadline')->nullable();
            $table->timestamp('proof_submitted_at')->nullable();
            $table->unsignedTinyInteger('rework_count')->default(0);
            $table->string('cancelled_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['technician_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
