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
        Schema::create('family_invitation_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families');
            $table->foreignId('created_by')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
            $table->timestampTz('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users');
            $table->timestampTz('revoked_at')->nullable();
            $table->index(['family_id', 'created_at']);
            $table->foreign(['family_id', 'created_by'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->foreign(['family_id', 'used_by'])->references(['family_id', 'user_id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE family_invitation_codes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE family_invitation_codes FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON family_invitation_codes USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_invitation_codes');
    }
};
