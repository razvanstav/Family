<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('created_by')->constrained('users');
            $table->string('name', 100);
            $table->string('make', 60);
            $table->string('model', 60);
            $table->string('registration', 24)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->uuid('photo_asset_id')->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'archived_at']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->foreign(['family_id', 'photo_asset_id'])->references(['family_id', 'id'])->on('media_assets');
        });
        Schema::create('vehicle_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('vehicle_id');
            $table->foreignId('author_id')->constrained('users');
            $table->string('kind', 20);
            $table->string('title', 160);
            $table->string('state', 12);
            $table->date('event_date');
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'vehicle_id', 'state', 'event_date', 'id']);
            $table->foreign(['family_id', 'vehicle_id'])->references(['family_id', 'id'])->on('vehicles');
        });
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('vehicle_id');
            $table->uuid('document_id');
            $table->primary(['vehicle_id', 'document_id']);
            $table->foreign(['family_id', 'vehicle_id'])->references(['family_id', 'id'])->on('vehicles');
            $table->foreign(['family_id', 'document_id'])->references(['family_id', 'id'])->on('documents');
        });
        DB::statement("ALTER TABLE vehicle_entries ADD CONSTRAINT vehicle_entry_kind CHECK (kind IN ('itp', 'rca', 'rovinieta', 'service', 'fuel', 'other'))");
        DB::statement("ALTER TABLE vehicle_entries ADD CONSTRAINT vehicle_entry_state CHECK (state IN ('planned', 'completed'))");
        DB::statement('ALTER TABLE vehicle_entries ADD CONSTRAINT vehicle_entry_numbers CHECK ((cost IS NULL OR cost >= 0) AND (odometer_km IS NULL OR odometer_km >= 0) AND version > 0)');
        DB::statement("ALTER TABLE vehicle_entries ADD CONSTRAINT vehicle_entry_planned CHECK (state = 'completed' OR (cost IS NULL AND odometer_km IS NULL))");
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicle_version CHECK (version > 0)');
        foreach (['vehicles', 'vehicle_entries', 'vehicle_documents'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('vehicle_entries');
        Schema::dropIfExists('vehicles');
    }
};
