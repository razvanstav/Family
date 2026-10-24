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
        Schema::table('calendar_reminder_deliveries', function (Blueprint $table) {
            $table->boolean('in_app_enabled')->default(true);
            $table->string('email_state', 12)->nullable();
            $table->unsignedInteger('email_attempts')->default(0);
            $table->timestampTz('email_claimed_at')->nullable();
            $table->timestampTz('email_sent_at')->nullable();
            $table->timestampTz('email_next_attempt_at')->nullable();
            $table->string('email_error_code', 48)->nullable();
            $table->index(['family_id', 'email_state', 'email_next_attempt_at'], 'calendar_reminder_email_pending');
        });
        DB::statement("ALTER TABLE calendar_reminder_deliveries ADD CONSTRAINT calendar_email_state CHECK (email_state IS NULL OR email_state IN ('pending', 'sending', 'sent', 'uncertain', 'cancelled', 'expired'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE calendar_reminder_deliveries DROP CONSTRAINT calendar_email_state');
        Schema::table('calendar_reminder_deliveries', function (Blueprint $table) {
            $table->dropIndex('calendar_reminder_email_pending');
            $table->dropColumn(['in_app_enabled', 'email_state', 'email_attempts', 'email_claimed_at', 'email_sent_at', 'email_next_attempt_at', 'email_error_code']);
        });
    }
};
