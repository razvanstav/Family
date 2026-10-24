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
        Schema::create('calendar_reminder_syncs', function (Blueprint $table) {
            $table->foreignUuid('family_id')->primary()->constrained('families')->cascadeOnDelete();
            $table->boolean('dirty')->default(true);
            $table->date('reconciled_on')->nullable();
        });
        DB::statement('ALTER TABLE calendar_reminder_syncs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE calendar_reminder_syncs FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON calendar_reminder_syncs USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_reminder_syncs');
    }
};
