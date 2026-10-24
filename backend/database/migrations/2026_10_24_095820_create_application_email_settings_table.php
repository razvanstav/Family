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
        Schema::create('application_email_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('username', 254)->default('');
            $table->text('password_encrypted')->nullable();
            $table->string('from_address', 254)->default('');
            $table->string('from_name', 120)->default('');
            $table->string('reply_to', 254)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::table('application_email_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::statement('ALTER TABLE application_email_settings ADD CONSTRAINT email_settings_singleton CHECK (id = 1 AND version > 0)');
        DB::statement('ALTER TABLE application_email_settings ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE application_email_settings FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY email_settings_read ON application_email_settings FOR SELECT USING (current_setting('app.email_settings_access', true) IN ('admin', 'delivery'))");
        DB::statement("CREATE POLICY email_settings_update ON application_email_settings FOR UPDATE USING (current_setting('app.email_settings_access', true) = 'admin') WITH CHECK (current_setting('app.email_settings_access', true) = 'admin')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_email_settings');
    }
};
