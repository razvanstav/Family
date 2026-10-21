<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('visibility', 12)->default('private');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_example')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'id']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->index(['family_id', 'created_at', 'id']);
        });
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('recipe_id');
            $table->unsignedInteger('version_no');
            $table->string('title', 160);
            $table->string('category', 20);
            $table->jsonb('ingredients');
            $table->text('instructions');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('servings');
            $table->string('difficulty', 12);
            $table->uuid('photo_asset_id')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'id']);
            $table->unique(['family_id', 'recipe_id', 'version_no']);
            $table->unique(['family_id', 'recipe_id', 'id']);
            $table->foreign(['family_id', 'recipe_id'])->references(['family_id', 'id'])->on('recipes');
            $table->foreign(['family_id', 'photo_asset_id'])->references(['family_id', 'id'])->on('media_assets');
        });
        foreach (['recipes', 'recipe_versions'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
        DB::statement("ALTER TABLE recipes ADD CONSTRAINT recipe_visibility CHECK (visibility IN ('private','family'))");
        DB::statement('ALTER TABLE recipes ADD CONSTRAINT recipe_version_positive CHECK (version > 0)');
        DB::statement("ALTER TABLE recipe_versions ADD CONSTRAINT recipe_values CHECK (version_no > 0 AND duration_minutes BETWEEN 1 AND 1440 AND servings BETWEEN 1 AND 50 AND difficulty IN ('easy','medium','advanced') AND category IN ('main','soup','dessert','breakfast','salad','snack') AND jsonb_typeof(ingredients) = 'array')");
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_versions');
        Schema::dropIfExists('recipes');
    }
};
