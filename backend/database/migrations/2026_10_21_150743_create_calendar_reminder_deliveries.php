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
            $table->unsignedSmallInteger('reminder_offset_minutes')->nullable();
            $table->unique(['family_id', 'id']);
        });
        Schema::create('calendar_reminder_recipients', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('calendar_item_id');
            $table->uuid('membership_id');
            $table->primary(['calendar_item_id', 'membership_id']);
            $table->foreign(['family_id', 'calendar_item_id'])->references(['family_id', 'id'])->on('calendar_items')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        Schema::create('calendar_reminder_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('calendar_item_id');
            $table->uuid('membership_id');
            $table->unsignedInteger('source_version');
            $table->timestampTz('due_at');
            $table->timestampTz('expires_at');
            $table->string('state', 12)->default('pending');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->unique(['calendar_item_id', 'source_version', 'membership_id']);
            $table->index(['family_id', 'state', 'due_at']);
            $table->foreign(['family_id', 'calendar_item_id'])->references(['family_id', 'id'])->on('calendar_items')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE calendar_items ADD CONSTRAINT calendar_reminder_offset CHECK (reminder_offset_minutes IS NULL OR reminder_offset_minutes IN (0,15,60,1440))');
        DB::statement("ALTER TABLE calendar_reminder_deliveries ADD CONSTRAINT calendar_delivery_state CHECK (state IN ('pending','delivered','cancelled','expired'))");
        foreach (['calendar_reminder_recipients', 'calendar_reminder_deliveries'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_reminder_deliveries');
        Schema::dropIfExists('calendar_reminder_recipients');
        DB::statement('ALTER TABLE calendar_items DROP CONSTRAINT calendar_reminder_offset');
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn('reminder_offset_minutes');
            $table->dropUnique(['family_id', 'id']);
        });
    }
};
