<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (['Lowokwaru', 'Klojen', 'Blimbing', 'Sukun', 'Kedungkandang', 'Dinoyo', 'Sawojajar', 'Tlogomas', 'Batu', 'Singosari'] as $order => $name) {
            DB::table('areas')->insert(['name' => $name, 'is_active' => true, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
