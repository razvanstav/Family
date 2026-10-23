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
        Schema::create('genealogy_people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families');
            $table->uuid('membership_id')->nullable();
            $table->string('name', 100)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['family_id', 'id']);
            $table->unique(['family_id', 'membership_id']);
            $table->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE genealogy_people ADD CONSTRAINT genealogy_person_identity CHECK ((membership_id IS NULL AND name IS NOT NULL) OR (membership_id IS NOT NULL AND membership_id = id AND name IS NULL))');
        Schema::create('genealogy_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('from_id');
            $table->uuid('to_id');
            $table->string('kind', 12);
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('removed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['family_id', 'kind', 'from_id', 'to_id']);
            $table->foreign(['family_id', 'from_id'])->references(['family_id', 'id'])->on('genealogy_people');
            $table->foreign(['family_id', 'to_id'])->references(['family_id', 'id'])->on('genealogy_people');
        });
        DB::statement("ALTER TABLE genealogy_relations ADD CONSTRAINT genealogy_relation_kind CHECK (kind IN ('parent', 'partner', 'sibling'))");
        DB::statement('ALTER TABLE genealogy_relations ADD CONSTRAINT genealogy_relation_distinct CHECK (from_id <> to_id)');
        DB::statement("ALTER TABLE genealogy_relations ADD CONSTRAINT genealogy_symmetric_order CHECK (kind = 'parent' OR from_id < to_id)");
        foreach (['genealogy_people', 'genealogy_relations'] as $table) {
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
        Schema::dropIfExists('genealogy_relations');
        Schema::dropIfExists('genealogy_people');
    }
};
