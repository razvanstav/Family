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
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->uuid('membership_id')->primary();
            $table->uuid('family_id');
            $table->date('birth_date')->nullable();
            $table->uuid('photo_asset_id')->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['family_id', 'membership_id']);
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
            $table->foreign(['family_id', 'photo_asset_id'])->references(['family_id', 'id'])->on('media_assets');
        });
        Schema::create('member_profile_parents', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('membership_id');
            $table->uuid('parent_membership_id');
            $table->primary(['membership_id', 'parent_membership_id']);
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'membership_id'])->on('member_profiles')->cascadeOnDelete();
            $table->foreign(['family_id', 'parent_membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        Schema::create('member_profile_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('membership_id');
            $table->foreignId('author_id')->constrained('users');
            $table->string('kind', 20);
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->date('occurred_on');
            $table->string('visibility', 12)->default('private');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'membership_id', 'occurred_on', 'id']);
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
            $table->foreign(['family_id', 'author_id'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        DB::statement("ALTER TABLE member_profile_entries ADD CONSTRAINT member_profile_entry_kind CHECK (kind IN ('visit', 'medication', 'book', 'culture', 'activity'))");
        DB::statement("ALTER TABLE member_profile_entries ADD CONSTRAINT member_profile_entry_visibility CHECK (visibility IN ('private', 'family'))");
        DB::statement('ALTER TABLE member_profile_entries ADD CONSTRAINT member_profile_entry_version CHECK (version > 0)');
        DB::statement('ALTER TABLE member_profiles ADD CONSTRAINT member_profile_version CHECK (version > 0)');
        DB::statement('ALTER TABLE member_profile_parents ADD CONSTRAINT member_profile_parent_distinct CHECK (membership_id <> parent_membership_id)');
        foreach (['member_profiles', 'member_profile_parents', 'member_profile_entries'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_profile_entries');
        Schema::dropIfExists('member_profile_parents');
        Schema::dropIfExists('member_profiles');
    }
};
