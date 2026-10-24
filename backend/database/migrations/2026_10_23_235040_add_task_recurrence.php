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
        Schema::table('family_tasks', function (Blueprint $table) {
            $table->string('recurrence', 12)->nullable();
            $table->date('recurrence_anchor_on')->nullable();
            $table->uuid('recurrence_parent_id')->nullable();
            $table->unique(['family_id', 'id']);
            $table->unique(['family_id', 'recurrence_parent_id']);
            $table->foreign(['family_id', 'recurrence_parent_id'])->references(['family_id', 'id'])->on('family_tasks');
        });
        DB::statement("ALTER TABLE family_tasks ADD CONSTRAINT task_recurrence CHECK ((recurrence IS NULL AND recurrence_anchor_on IS NULL) OR (recurrence IS NOT NULL AND recurrence IN ('daily', 'weekly', 'monthly', 'yearly') AND due_on IS NOT NULL AND recurrence_anchor_on IS NOT NULL AND recurrence_anchor_on BETWEEN '1900-01-01' AND due_on))");
        DB::statement('ALTER TABLE family_tasks ADD CONSTRAINT task_recurrence_parent CHECK (recurrence_parent_id IS NULL OR recurrence_parent_id <> id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE family_tasks DROP CONSTRAINT task_recurrence');
        DB::statement('ALTER TABLE family_tasks DROP CONSTRAINT task_recurrence_parent');
        Schema::table('family_tasks', function (Blueprint $table) {
            $table->dropForeign(['family_id', 'recurrence_parent_id']);
            $table->dropUnique(['family_id', 'recurrence_parent_id']);
            $table->dropUnique(['family_id', 'id']);
            $table->dropColumn(['recurrence', 'recurrence_anchor_on', 'recurrence_parent_id']);
        });
    }
};
