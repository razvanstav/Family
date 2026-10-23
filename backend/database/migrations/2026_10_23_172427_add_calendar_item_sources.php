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
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->string('source_type', 32)->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('source_parent_id')->nullable();
            $table->unsignedInteger('source_version')->nullable();
            $table->unique(['family_id', 'source_type', 'source_id']);
        });
        DB::statement("ALTER TABLE calendar_items ADD CONSTRAINT calendar_source_fields CHECK ((source_type IS NULL AND source_id IS NULL AND source_parent_id IS NULL AND source_version IS NULL) OR (source_type IS NOT NULL AND source_id IS NOT NULL AND source_parent_id IS NOT NULL AND source_version IS NOT NULL AND source_version > 0 AND visibility = 'family' AND all_day))");
        DB::statement('ALTER TABLE calendar_items DROP CONSTRAINT calendar_reminder_offset');
        DB::statement('ALTER TABLE calendar_items ALTER COLUMN reminder_offset_minutes TYPE integer');
        DB::statement('ALTER TABLE calendar_items ADD CONSTRAINT calendar_reminder_offset CHECK (reminder_offset_minutes IS NULL OR reminder_offset_minutes IN (0,15,60,1440,10080,43200))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE calendar_items DROP CONSTRAINT calendar_source_fields');
        DB::statement('ALTER TABLE calendar_items DROP CONSTRAINT calendar_reminder_offset');
        DB::statement('UPDATE calendar_items SET reminder_offset_minutes = NULL WHERE reminder_offset_minutes IN (10080,43200)');
        DB::statement('ALTER TABLE calendar_items ADD CONSTRAINT calendar_reminder_offset CHECK (reminder_offset_minutes IS NULL OR reminder_offset_minutes IN (0,15,60,1440))');
        DB::statement('DELETE FROM calendar_items WHERE source_type IS NOT NULL');
        DB::statement('ALTER TABLE calendar_items ALTER COLUMN reminder_offset_minutes TYPE smallint');
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropUnique(['family_id', 'source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id', 'source_parent_id', 'source_version']);
        });
    }
};
