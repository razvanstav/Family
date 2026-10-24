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
        Schema::create('memory_moment_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('moment_id');
            $table->uuid('media_asset_id');
            $table->unsignedSmallInteger('position');
            $table->unique(['moment_id', 'position']);
            $table->unique(['moment_id', 'media_asset_id']);
            $table->foreign(['family_id', 'moment_id'])->references(['family_id', 'id'])->on('memory_moments')->cascadeOnDelete();
            $table->foreign(['family_id', 'media_asset_id'])->references(['family_id', 'id'])->on('media_assets');
        });
        DB::statement('INSERT INTO memory_moment_photos (id, family_id, moment_id, media_asset_id, position) SELECT gen_random_uuid(), family_id, id, photo_asset_id, 0 FROM memory_moments WHERE photo_asset_id IS NOT NULL');
        DB::statement('ALTER TABLE memory_moment_photos ADD CONSTRAINT memory_photo_position CHECK (position BETWEEN 0 AND 19)');
        DB::statement('ALTER TABLE memory_moment_photos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE memory_moment_photos FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON memory_moment_photos USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_moment_photos');
    }
};
