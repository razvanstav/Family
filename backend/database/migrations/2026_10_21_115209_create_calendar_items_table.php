<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->string('visibility', 12)->default('private');
            $table->date('event_date');
            $table->boolean('all_day');
            $table->timestampTz('starts_at')->nullable();
            $table->string('timezone', 64)->default('Europe/Bucharest');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'event_date']);
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        DB::statement("ALTER TABLE calendar_items ADD CONSTRAINT calendar_visibility CHECK (visibility IN ('private','family'))");
        DB::statement('ALTER TABLE calendar_items ADD CONSTRAINT calendar_time CHECK ((all_day AND starts_at IS NULL) OR (NOT all_day AND starts_at IS NOT NULL))');
        DB::statement('ALTER TABLE calendar_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE calendar_items FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON calendar_items USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_items');
    }
};
