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
        Schema::create('pets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('created_by');
            $table->string('name', 100);
            $table->string('species', 60);
            $table->string('breed', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->uuid('photo_asset_id')->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'archived_at']);
            $table->foreign(['family_id', 'created_by'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->foreign(['family_id', 'photo_asset_id'])->references(['family_id', 'id'])->on('media_assets');
        });
        Schema::create('pet_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('pet_id');
            $table->foreignId('author_id');
            $table->string('kind', 20);
            $table->string('title', 160);
            $table->date('event_date');
            $table->decimal('weight_kg', 7, 3)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->date('next_due_on')->nullable();
            $table->timestampTz('next_due_completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'pet_id', 'archived_at', 'event_date', 'id']);
            $table->foreign(['family_id', 'pet_id'])->references(['family_id', 'id'])->on('pets');
            $table->foreign(['family_id', 'author_id'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        Schema::create('pet_documents', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('pet_id');
            $table->uuid('document_id');
            $table->primary(['pet_id', 'document_id']);
            $table->foreign(['family_id', 'pet_id'])->references(['family_id', 'id'])->on('pets');
            $table->foreign(['family_id', 'document_id'])->references(['family_id', 'id'])->on('documents');
        });
        DB::statement("ALTER TABLE pets ADD CONSTRAINT pet_content CHECK (length(btrim(name)) > 0 AND length(btrim(species)) > 0 AND version > 0 AND (birth_date IS NULL OR birth_date BETWEEN '1900-01-01' AND '2099-12-31'))");
        DB::statement("ALTER TABLE pet_entries ADD CONSTRAINT pet_entry_kind CHECK (kind IN ('weight', 'vaccination', 'visit', 'care'))");
        DB::statement('ALTER TABLE pet_entries ADD CONSTRAINT pet_entry_content CHECK (length(btrim(title)) > 0 AND version > 0 AND (notes IS NULL OR length(notes) <= 4000))');
        DB::statement("ALTER TABLE pet_entries ADD CONSTRAINT pet_entry_numbers CHECK (((kind = 'weight' AND weight_kg IS NOT NULL AND weight_kg > 0 AND weight_kg <= 9999.999) OR (kind <> 'weight' AND weight_kg IS NULL)) AND (cost IS NULL OR cost BETWEEN 0 AND 9999999.99))");
        DB::statement("ALTER TABLE pet_entries ADD CONSTRAINT pet_entry_dates CHECK (event_date BETWEEN '1900-01-01' AND '2099-12-31' AND (next_due_on IS NULL OR next_due_on BETWEEN event_date AND '2099-12-31') AND (next_due_on IS NOT NULL OR next_due_completed_at IS NULL))");
        foreach (['pets', 'pet_entries', 'pet_documents'] as $table) {
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
        Schema::dropIfExists('pet_documents');
        Schema::dropIfExists('pet_entries');
        Schema::dropIfExists('pets');
    }
};
