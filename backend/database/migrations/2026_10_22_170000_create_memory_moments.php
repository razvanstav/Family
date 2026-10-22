<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_moments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('kind', 12);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->date('occurred_on');
            $table->string('visibility', 12)->default('private');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'occurred_on', 'id']);
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
        });

        Schema::create('memory_moment_people', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('moment_id');
            $table->uuid('membership_id');
            $table->primary(['moment_id', 'membership_id']);
            $table->foreign(['family_id', 'moment_id'])->references(['family_id', 'id'])->on('memory_moments')->cascadeOnDelete();
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });

        DB::statement("ALTER TABLE memory_moments ADD CONSTRAINT memory_moment_kind CHECK (kind IN ('moment', 'note'))");
        DB::statement("ALTER TABLE memory_moments ADD CONSTRAINT memory_moment_visibility CHECK (visibility IN ('private', 'family'))");
        DB::statement('ALTER TABLE memory_moments ADD CONSTRAINT memory_moment_version CHECK (version > 0)');
        foreach (['memory_moments', 'memory_moment_people'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_moment_people');
        Schema::dropIfExists('memory_moments');
    }
};
