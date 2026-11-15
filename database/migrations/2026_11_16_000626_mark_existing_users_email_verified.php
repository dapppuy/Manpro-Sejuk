<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts made before email verification was required stay usable.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * Verification dates cannot be told apart from real ones, so nothing is undone.
     */
    public function down(): void
    {
        //
    }
};
