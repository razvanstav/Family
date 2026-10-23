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
        Schema::create('family_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id');
            $table->uuid('assignee_membership_id');
            $table->string('title', 160);
            $table->date('due_on')->nullable();
            $table->string('visibility', 12)->default('family');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'visibility', 'completed_at', 'due_on', 'id']);
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->foreign(['family_id', 'assignee_membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement("ALTER TABLE family_tasks ADD CONSTRAINT task_visibility CHECK (visibility IN ('family', 'private'))");
        DB::statement("ALTER TABLE family_tasks ADD CONSTRAINT task_content CHECK (length(btrim(title)) > 0 AND version > 0 AND (due_on IS NULL OR due_on BETWEEN '1900-01-01' AND '2099-12-31'))");
        DB::statement('ALTER TABLE family_tasks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE family_tasks FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON family_tasks USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_tasks');
    }
};
