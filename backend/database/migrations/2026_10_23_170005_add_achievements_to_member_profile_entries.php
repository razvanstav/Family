<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE member_profile_entries DROP CONSTRAINT member_profile_entry_kind');
        DB::statement("ALTER TABLE member_profile_entries ADD CONSTRAINT member_profile_entry_kind CHECK (kind IN ('visit', 'medication', 'book', 'culture', 'activity', 'achievement'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('member_profile_entries')->where('kind', 'achievement')->exists()) {
            throw new RuntimeException('Migrația nu poate fi retrasă cât există realizări înregistrate.');
        }
        DB::statement('ALTER TABLE member_profile_entries DROP CONSTRAINT member_profile_entry_kind');
        DB::statement("ALTER TABLE member_profile_entries ADD CONSTRAINT member_profile_entry_kind CHECK (kind IN ('visit', 'medication', 'book', 'culture', 'activity'))");
    }
};
