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
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('recipient', 254);
            $table->string('subject', 255);
            $table->string('status', 6);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('created_at');
            $table->index(['created_at', 'id']);
        });
        DB::statement("ALTER TABLE email_deliveries ADD CONSTRAINT email_delivery_status CHECK ((status = 'sent' AND sent_at IS NOT NULL) OR (status = 'failed' AND sent_at IS NULL))");
        DB::statement('ALTER TABLE email_deliveries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE email_deliveries FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY email_deliveries_admin_read ON email_deliveries FOR SELECT USING (current_setting('app.email_settings_access', true) = 'admin')");
        DB::statement("CREATE POLICY email_deliveries_append ON email_deliveries FOR INSERT WITH CHECK (current_setting('app.email_settings_access', true) = 'delivery')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
