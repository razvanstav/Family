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
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->foreignId('owner_id');
            $table->uuid('payer_membership_id');
            $table->unsignedInteger('amount_bani');
            $table->string('category', 16);
            $table->date('spent_on');
            $table->string('note', 240)->nullable();
            $table->string('visibility', 12)->default('family');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'visibility', 'spent_on', 'id']);
            $table->foreign(['family_id', 'owner_id'])->references(['family_id', 'user_id'])->on('family_memberships');
            $table->foreign(['family_id', 'payer_membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expense_amount CHECK (amount_bani BETWEEN 1 AND 99999999 AND version > 0)');
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expense_visibility CHECK (visibility IN ('family', 'private'))");
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expense_category CHECK (category IN ('food', 'home', 'transport', 'health', 'children', 'leisure', 'other'))");
        DB::statement('ALTER TABLE expenses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE expenses FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON expenses USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
