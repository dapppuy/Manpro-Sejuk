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
        if (!Schema::hasTable('customer_units')) {
            Schema::create('customer_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('brand')->nullable();
                $table->string('capacity_pk')->nullable();
                $table->string('ac_type')->nullable();
                $table->string('serial_number')->nullable();
                $table->date('last_serviced_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_unit_id')->nullable()->constrained('customer_units')->nullOnDelete();
            $table->text('complaint_text');
            $table->string('sticker_photo_path')->nullable();
            $table->string('condition_photo_path')->nullable();

            $table->json('detected_unit_info')->nullable();
            $table->json('possible_causes')->nullable();
            $table->string('confidence_level')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('urgency_level')->nullable();
            $table->string('recommended_service_slug')->nullable();
            $table->text('preliminary_advice')->nullable();
            $table->json('raw_ai_response')->nullable();

            $table->text('actual_cause_by_technician')->nullable();
            $table->boolean('is_accurate')->nullable();

            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'customer_unit_id')) {
                $table->foreignId('customer_unit_id')->nullable()
                    ->constrained('customer_units')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'diagnosis_id')) {
                $table->foreignId('diagnosis_id')->nullable()
                    ->constrained('diagnoses')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'diagnosis_id')) {
                $table->dropConstrainedForeignId('diagnosis_id');
            }
            if (Schema::hasColumn('orders', 'customer_unit_id')) {
                $table->dropConstrainedForeignId('customer_unit_id');
            }
        });

        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('customer_units');
    }
};
