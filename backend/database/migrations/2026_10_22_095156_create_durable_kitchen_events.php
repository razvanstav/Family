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
        Schema::table('kitchen_meals', fn (Blueprint $table) => $table->unsignedBigInteger('activity_revision')->default(1));
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->string('name', 80);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('source_kind', 40);
            $table->uuid('source_id');
            $table->unsignedBigInteger('source_version');
            $table->timestamp('occurred_at');
            $table->foreign('family_id')->references('id')->on('families');
            $table->unique(['family_id', 'id']);
            $table->unique(['family_id', 'source_kind', 'source_id', 'source_version'], 'outbox_source_version_unique');
        });
        Schema::create('outbox_deliveries', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('event_id');
            $table->string('handler', 80);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error', 40)->nullable();
            $table->primary(['event_id', 'handler']);
            $table->foreign(['family_id', 'event_id'])->references(['family_id', 'id'])->on('outbox_events')->cascadeOnDelete();
            $table->index(['family_id', 'delivered_at', 'available_at']);
        });
        Schema::create('kitchen_activity_sources', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->string('source_kind', 40);
            $table->uuid('source_id');
            $table->unsignedBigInteger('source_version');
            $table->timestamp('occurred_at');
            $table->boolean('removed');
            $table->primary(['family_id', 'source_kind', 'source_id']);
            $table->foreign('family_id')->references('id')->on('families');
            $table->index(['family_id', 'occurred_at', 'source_kind', 'source_id'], 'kitchen_activity_order');
        });
        DB::statement('ALTER TABLE kitchen_meals ADD CONSTRAINT kitchen_activity_revision_positive CHECK (activity_revision > 0)');
        DB::statement('ALTER TABLE outbox_events ADD CONSTRAINT outbox_versions_positive CHECK (schema_version > 0 AND source_version > 0)');
        DB::statement('ALTER TABLE outbox_deliveries ADD CONSTRAINT outbox_attempts_nonnegative CHECK (attempts >= 0)');
        DB::statement("ALTER TABLE kitchen_activity_sources ADD CONSTRAINT kitchen_activity_values CHECK (source_kind IN ('kitchen.recipe','kitchen.meal') AND source_version > 0)");
        foreach (['outbox_events', 'outbox_deliveries', 'kitchen_activity_sources'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
        }
        DB::unprepared("CREATE FUNCTION outbox_immutable_event() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Outbox events are immutable' USING ERRCODE = 'check_violation'; END; $$");
        DB::statement('CREATE TRIGGER outbox_events_immutable BEFORE UPDATE ON outbox_events FOR EACH ROW EXECUTE FUNCTION outbox_immutable_event()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_activity_sources');
        Schema::dropIfExists('outbox_deliveries');
        Schema::dropIfExists('outbox_events');
        DB::statement('DROP FUNCTION IF EXISTS outbox_immutable_event()');
        Schema::table('kitchen_meals', fn (Blueprint $table) => $table->dropColumn('activity_revision'));
    }
};
