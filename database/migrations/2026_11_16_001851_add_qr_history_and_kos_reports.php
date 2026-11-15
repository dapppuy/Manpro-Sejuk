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
        Schema::table('customer_units', function (Blueprint $table) {
            $table->string('qr_token', 36)->unique()->nullable()->after('id');
            $table->string('room_label')->nullable()->after('name'); // Misal: "Kamar Kos A-03"
            $table->boolean('is_kos_unit')->default(false)->after('room_label');
            $table->string('sticker_photo_path')->nullable()->after('serial_number');
        });

        // Log riwayat servis yang melekat pada unit AC fisik (F-11)
        Schema::create('unit_service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_unit_id')->constrained('customer_units')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('service_date');
            $table->string('service_type'); // Cuci AC, Perbaikan, Isi Freon, dll.
            $table->text('inspection_findings')->nullable();
            $table->text('actions_taken');
            $table->string('before_photo_path')->nullable();
            $table->string('after_photo_path')->nullable();
            $table->date('next_recommended_service_date')->nullable();
            $table->timestamps();
        });

        // Laporan kerusakan dari penghuni kos via scan QR (PRD 6.2 & 13.3)
        Schema::create('unit_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_unit_id')->constrained('customer_units')->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name');
            $table->string('reporter_contact')->nullable();
            $table->text('issue_description');
            $table->string('photo_path')->nullable();
            $table->string('status')->default('menunggu_pengelola'); // menunggu_pengelola | diproses_order | selesai | ditolak
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_issue_reports');
        Schema::dropIfExists('unit_service_logs');
        Schema::table('customer_units', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'room_label', 'is_kos_unit', 'sticker_photo_path']);
        });
    }
};
