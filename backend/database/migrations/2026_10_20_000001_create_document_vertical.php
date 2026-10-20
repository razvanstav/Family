<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->unsignedBigInteger('authz_revision')->default(1);
            $t->timestamps();
        });
        // Identity bootstrap tables: explicitly actor-scoped before entering tenant context.
        Schema::create('family_memberships', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('family_id');
            $t->foreign('family_id')->references('id')->on('families');
            $t->foreignId('user_id')->constrained();
            $t->string('role', 12);
            $t->timestamp('revoked_at')->nullable();
            $t->unique(['family_id', 'user_id']);
            $t->unique(['family_id', 'id']);
        });
        Schema::create('media_assets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('family_id');
            $t->foreignId('created_by')->constrained('users');
            $t->string('storage_key')->unique();
            $t->string('original_name');
            $t->string('mime', 100);
            $t->unsignedBigInteger('bytes');
            $t->string('sha256', 64);
            $t->string('status', 16)->default('quarantine');
            $t->timestamps();
            $t->unique(['family_id', 'id']);
            $t->foreign(['family_id', 'created_by'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('family_id');
            $t->foreignId('owner_id')->constrained('users');
            $t->uuid('asset_id')->unique();
            $t->string('title', 160);
            $t->string('category', 20)->default('personal');
            $t->string('visibility', 12)->default('private');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['family_id', 'id']);
            $t->foreign(['family_id', 'asset_id'])->references(['family_id', 'id'])->on('media_assets');
            $t->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        Schema::create('document_grants', function (Blueprint $t) {
            $t->uuid('family_id');
            $t->uuid('document_id');
            $t->uuid('membership_id');
            $t->primary(['document_id', 'membership_id']);
            $t->foreign(['family_id', 'document_id'])->references(['family_id', 'id'])->on('documents')->cascadeOnDelete();
            $t->foreign(['family_id', 'membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('family_id');
            $t->foreignId('actor_id')->constrained('users');
            $t->string('action', 50);
            $t->uuid('resource_id');
            $t->timestamp('created_at');
            $t->foreign('family_id')->references('id')->on('families');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE documents ADD CONSTRAINT document_visibility CHECK (visibility IN ('private','family','selected'))");
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT asset_status CHECK (status IN ('quarantine','ready','rejected'))");
            DB::statement("ALTER TABLE family_memberships ADD CONSTRAINT membership_role CHECK (role IN ('owner','adult','limited'))");
            foreach (['documents', 'media_assets', 'document_grants', 'audit_events'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_boundary ON {$table} USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
            }
        }
    }

    public function down(): void
    {
        foreach (['audit_events', 'document_grants', 'documents', 'media_assets', 'family_memberships', 'families'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
