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
        Schema::create('memory_timeline_sources', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->string('source_kind', 40);
            $table->uuid('source_id');
            $table->unsignedBigInteger('source_version');
            $table->date('occurred_on');
            $table->boolean('removed');
            $table->primary(['family_id', 'source_kind', 'source_id']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->index(['family_id', 'occurred_on', 'source_kind', 'source_id'], 'memory_timeline_order');
        });
        DB::statement("ALTER TABLE memory_timeline_sources ADD CONSTRAINT memory_timeline_values CHECK (source_kind IN ('memory.moment', 'kitchen.meal') AND source_version > 0)");
        DB::statement('ALTER TABLE memory_timeline_sources ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE memory_timeline_sources FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON memory_timeline_sources USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_timeline_sources');
    }
};
