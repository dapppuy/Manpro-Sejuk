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
        Schema::create('technician_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->unique()->constrained('technician_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('years');
            $table->string('reference_phone', 20);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('technician_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->string('area_name');
        });

        Schema::create('technician_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->string('brand', 50);
        });

        Schema::create('technician_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unique(['technician_id', 'service_id']);
        });

        Schema::create('technician_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('note');
            $table->unsignedInteger('price_min');
            $table->unsignedInteger('price_max');
            $table->timestamps();
        });

        Schema::create('technician_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->date('date')->nullable();
            $table->string('time_window', 10);
            $table->boolean('is_available')->default(true);
            $table->index(['technician_id', 'day_of_week']);
        });

        Schema::create('technician_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->unique()->constrained('technician_profiles')->cascadeOnDelete();
            $table->string('bank_name', 50);
            $table->string('account_name');
            $table->string('account_number', 30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technician_payout_accounts');
        Schema::dropIfExists('technician_availabilities');
        Schema::dropIfExists('technician_photos');
        Schema::dropIfExists('technician_prices');
        Schema::dropIfExists('technician_services');
        Schema::dropIfExists('technician_brands');
        Schema::dropIfExists('technician_service_areas');
        Schema::dropIfExists('technician_experiences');
        Schema::dropIfExists('technician_documents');
    }
};
