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
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('payment_status', 12)->default('paid');
            $table->string('recurrence', 12)->nullable();
            $table->date('recurrence_anchor_on')->nullable();
            $table->uuid('recurrence_parent_id')->nullable();
            $table->unique(['family_id', 'id']);
            $table->unique(['family_id', 'recurrence_parent_id']);
            $table->foreign(['family_id', 'recurrence_parent_id'])->references(['family_id', 'id'])->on('expenses');
            $table->index(['family_id', 'payment_status', 'spent_on', 'id']);
        });
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expense_payment_status CHECK (payment_status IN ('paid', 'planned'))");
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expense_recurrence CHECK ((recurrence IS NULL AND recurrence_anchor_on IS NULL) OR (recurrence IS NOT NULL AND recurrence IN ('daily', 'weekly', 'monthly', 'yearly') AND recurrence_anchor_on IS NOT NULL AND recurrence_anchor_on BETWEEN '1900-01-01' AND spent_on))");
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expense_recurrence_parent CHECK (recurrence_parent_id IS NULL OR recurrence_parent_id <> id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT expense_payment_status');
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT expense_recurrence');
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT expense_recurrence_parent');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['family_id', 'recurrence_parent_id']);
            $table->dropUnique(['family_id', 'recurrence_parent_id']);
            $table->dropUnique(['family_id', 'id']);
            $table->dropIndex(['family_id', 'payment_status', 'spent_on', 'id']);
            $table->dropColumn(['payment_status', 'recurrence', 'recurrence_anchor_on', 'recurrence_parent_id']);
        });
    }
};
