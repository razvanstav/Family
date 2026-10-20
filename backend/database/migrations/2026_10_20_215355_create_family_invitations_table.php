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
        Schema::create('family_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families');
            $table->string('email', 254);
            $table->string('role', 12);
            $table->foreignId('invited_by')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('created_at');
            $table->index(['family_id', 'email']);
        });
        DB::statement("ALTER TABLE family_invitations ADD CONSTRAINT invitation_role CHECK (role IN ('adult','limited'))");
        DB::statement('ALTER TABLE family_invitations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE family_invitations FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON family_invitations USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_invitations');
    }
};
