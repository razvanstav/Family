<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE FUNCTION kitchen_immutable_recipe_version() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Recipe versions are immutable' USING ERRCODE = 'check_violation'; END; $$");
        DB::statement('CREATE TRIGGER recipe_versions_immutable BEFORE UPDATE ON recipe_versions FOR EACH ROW EXECUTE FUNCTION kitchen_immutable_recipe_version()');
        Schema::create('kitchen_meals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id')->constrained('users');
            $table->uuid('recipe_id');
            $table->uuid('recipe_version_id');
            $table->string('dish_title', 160);
            $table->date('meal_date');
            $table->string('meal_type', 12);
            $table->string('visibility', 12)->default('family');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'meal_date', 'id']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->foreign(['family_id', 'recipe_id', 'recipe_version_id'])->references(['family_id', 'recipe_id', 'id'])->on('recipe_versions');
        });
        Schema::create('kitchen_meal_votes', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('meal_id');
            $table->uuid('membership_id');
            $table->boolean('liked')->nullable();
            $table->string('comment', 1000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->primary(['meal_id', 'membership_id']);
            $table->foreign(['family_id', 'meal_id'])->references(['family_id', 'id'])->on('kitchen_meals')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement("ALTER TABLE kitchen_meals ADD CONSTRAINT kitchen_meal_values CHECK (visibility IN ('private','family') AND meal_type IN ('breakfast','lunch','dinner','snack') AND meal_date BETWEEN DATE '1900-01-01' AND DATE '2100-12-31' AND version > 0)");
        DB::statement('ALTER TABLE kitchen_meal_votes ADD CONSTRAINT kitchen_vote_values CHECK (version > 0 AND ((withdrawn_at IS NULL AND liked IS NOT NULL) OR (withdrawn_at IS NOT NULL AND liked IS NULL AND comment IS NULL)))');
        foreach (['kitchen_meals', 'kitchen_meal_votes'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_meal_votes');
        Schema::dropIfExists('kitchen_meals');
        DB::statement('DROP TRIGGER recipe_versions_immutable ON recipe_versions');
        DB::statement('DROP FUNCTION kitchen_immutable_recipe_version()');
    }
};
