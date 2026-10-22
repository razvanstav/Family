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
        Schema::table('memory_moments', function (Blueprint $table) {
            $table->uuid('photo_asset_id')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->foreign(['family_id', 'photo_asset_id'])->references(['family_id', 'id'])->on('media_assets');
        });
        DB::statement('ALTER TABLE memory_moments DROP CONSTRAINT memory_moment_visibility');
        DB::statement("ALTER TABLE memory_moments ADD CONSTRAINT memory_moment_visibility CHECK (visibility IN ('private', 'family', 'selected'))");
        Schema::create('memory_moment_grants', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('moment_id');
            $table->uuid('membership_id');
            $table->primary(['moment_id', 'membership_id']);
            $table->foreign(['family_id', 'moment_id'])->references(['family_id', 'id'])->on('memory_moments')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE memory_moment_grants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE memory_moment_grants FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON memory_moment_grants USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_moment_grants');
        DB::statement('ALTER TABLE memory_moments DROP CONSTRAINT memory_moment_visibility');
        DB::statement("UPDATE memory_moments SET visibility = 'private' WHERE visibility = 'selected'");
        DB::statement("ALTER TABLE memory_moments ADD CONSTRAINT memory_moment_visibility CHECK (visibility IN ('private', 'family'))");
        Schema::table('memory_moments', function (Blueprint $table) {
            $table->dropForeign(['family_id', 'photo_asset_id']);
            $table->dropColumn(['photo_asset_id', 'archived_at']);
        });
    }
};
