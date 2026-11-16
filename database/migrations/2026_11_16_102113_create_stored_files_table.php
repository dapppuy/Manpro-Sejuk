<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holds uploaded files for the "database" filesystem driver, used on hosts whose
     * disk is not shared between requests (Vercel), so every request sees the same files.
     */
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32);
            $table->string('path', 700);
            $table->binary('contents');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('visibility', 16)->default('private');
            $table->timestamps();

            $table->unique(['disk', 'path']);
        });

        // A plain BLOB stops at 64 KB; photos need more.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stored_files MODIFY contents LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
