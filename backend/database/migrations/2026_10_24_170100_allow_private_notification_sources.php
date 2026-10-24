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
        DB::statement('ALTER TABLE calendar_items DROP CONSTRAINT calendar_source_fields');
        DB::statement("ALTER TABLE calendar_items ADD CONSTRAINT calendar_source_fields CHECK ((source_type IS NULL AND source_id IS NULL AND source_parent_id IS NULL AND source_version IS NULL) OR (source_type IS NOT NULL AND source_id IS NOT NULL AND source_parent_id IS NOT NULL AND source_version IS NOT NULL AND source_version > 0 AND (visibility = 'family' OR source_type IN ('task', 'document_expiry', 'memory_anniversary')) AND all_day))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM calendar_items WHERE source_type IN ('document_expiry', 'memory_anniversary')");
        DB::statement('ALTER TABLE calendar_items DROP CONSTRAINT calendar_source_fields');
        DB::statement("ALTER TABLE calendar_items ADD CONSTRAINT calendar_source_fields CHECK ((source_type IS NULL AND source_id IS NULL AND source_parent_id IS NULL AND source_version IS NULL) OR (source_type IS NOT NULL AND source_id IS NOT NULL AND source_parent_id IS NOT NULL AND source_version IS NOT NULL AND source_version > 0 AND (visibility = 'family' OR source_type = 'task') AND all_day))");
    }
};
