<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_restaurants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name', 160);
            $table->string('city', 120)->nullable();
            $table->string('identity_key', 64);
            $table->timestamps();
            $table->unique(['family_id', 'id']);
            $table->unique(['family_id', 'identity_key']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        Schema::create('kitchen_dining_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id')->constrained('users');
            $table->uuid('restaurant_id');
            $table->date('meal_date');
            $table->string('meal_type', 12)->default('dinner');
            $table->string('visibility', 12)->default('family');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'meal_date', 'id']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->foreign(['family_id', 'restaurant_id'])->references(['family_id', 'id'])->on('kitchen_restaurants');
        });
        Schema::create('kitchen_restaurant_access', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('restaurant_id');
            $table->uuid('membership_id');
            $table->primary(['restaurant_id', 'membership_id']);
            $table->foreign(['family_id', 'restaurant_id'])->references(['family_id', 'id'])->on('kitchen_restaurants')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE kitchen_dining_visits ADD CONSTRAINT kitchen_visit_values CHECK (visibility IN ('private','family') AND meal_type IN ('breakfast','lunch','dinner','snack') AND meal_date BETWEEN DATE '1900-01-01' AND DATE '2100-12-31' AND version > 0)");
        Schema::table('kitchen_meals', function (Blueprint $table) {
            $table->uuid('recipe_id')->nullable()->change();
            $table->uuid('recipe_version_id')->nullable()->change();
            $table->uuid('visit_id')->nullable();
            $table->string('category', 16)->default('other');
            $table->foreign(['family_id', 'visit_id'])->references(['family_id', 'id'])->on('kitchen_dining_visits');
            $table->index(['family_id', 'visit_id']);
        });
        DB::statement("ALTER TABLE kitchen_meals ADD CONSTRAINT kitchen_meal_dining_values CHECK ((recipe_id IS NULL) = (recipe_version_id IS NULL) AND (visit_id IS NULL OR recipe_id IS NULL) AND category IN ('pasta','soup','pizza','main','salad','side','dessert','snack','drink','other'))");
        foreach (['meal' => 'kitchen_meals', 'visit' => 'kitchen_dining_visits'] as $kind => $source) {
            Schema::create('kitchen_'.$kind.'_ratings', function (Blueprint $table) use ($kind, $source) {
                $table->uuid('family_id');
                $table->uuid($kind.'_id');
                $table->uuid('membership_id');
                $table->unsignedTinyInteger('score')->nullable();
                $table->string('comment', 1000)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamps();
                $table->primary([$kind.'_id', 'membership_id']);
                $table->foreign(['family_id', $kind.'_id'])->references(['family_id', 'id'])->on($source)->cascadeOnDelete();
                $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
            });
            DB::statement("ALTER TABLE kitchen_{$kind}_ratings ADD CONSTRAINT kitchen_{$kind}_rating_values CHECK (version > 0 AND ((withdrawn_at IS NULL AND score IS NOT NULL AND score BETWEEN 1 AND 10) OR (withdrawn_at IS NOT NULL AND score IS NULL AND comment IS NULL)))");
        }
        foreach (['kitchen_restaurants', 'kitchen_restaurant_access', 'kitchen_dining_visits', 'kitchen_meal_ratings', 'kitchen_visit_ratings'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_visit_ratings');
        Schema::dropIfExists('kitchen_meal_ratings');
        DB::statement('ALTER TABLE kitchen_meals DROP CONSTRAINT kitchen_meal_dining_values');
        Schema::table('kitchen_meals', function (Blueprint $table) {
            $table->dropForeign(['family_id', 'visit_id']);
            $table->dropIndex(['family_id', 'visit_id']);
            $table->dropColumn(['visit_id', 'category']);
        });
        Schema::dropIfExists('kitchen_dining_visits');
        Schema::dropIfExists('kitchen_restaurant_access');
        Schema::dropIfExists('kitchen_restaurants');
    }
};
