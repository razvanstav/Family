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
        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });
        DB::statement('UPDATE recipes SET revision = version');
        DB::statement('ALTER TABLE recipes ADD CONSTRAINT recipe_revision_positive CHECK (revision > 0)');
        DB::statement('ALTER TABLE recipes DROP CONSTRAINT recipe_visibility');
        DB::statement("ALTER TABLE recipes ADD CONSTRAINT recipe_visibility CHECK (visibility IN ('private','family','selected'))");
        Schema::create('recipe_grants', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('recipe_id');
            $table->uuid('membership_id');
            $table->primary(['recipe_id', 'membership_id']);
            $table->foreign(['family_id', 'recipe_id'])->references(['family_id', 'id'])->on('recipes')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE recipe_grants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE recipe_grants FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON recipe_grants USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_grants');
        DB::statement('ALTER TABLE recipes DROP CONSTRAINT recipe_visibility');
        DB::statement("UPDATE recipes SET visibility = 'private' WHERE visibility = 'selected'");
        DB::statement("ALTER TABLE recipes ADD CONSTRAINT recipe_visibility CHECK (visibility IN ('private','family'))");
        Schema::table('recipes', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};
