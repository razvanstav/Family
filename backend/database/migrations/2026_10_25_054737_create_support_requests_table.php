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
        Schema::create('support_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 16)->unique();
            $table->string('purpose', 20);
            $table->string('status', 12)->default('received');
            $table->text('payload_ciphertext');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->index(['created_at', 'id']);
            $table->index(['status', 'created_at', 'id']);
        });
        DB::statement("ALTER TABLE support_requests ADD CONSTRAINT support_request_purpose CHECK (purpose IN ('support', 'privacy', 'withdrawal', 'illegal-content'))");
        DB::statement("ALTER TABLE support_requests ADD CONSTRAINT support_request_status CHECK (status IN ('received', 'in_progress', 'closed') AND version > 0)");
        DB::statement('ALTER TABLE support_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE support_requests FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY support_requests_submit ON support_requests FOR INSERT WITH CHECK (current_setting('app.support_requests_access', true) = 'submission' AND status = 'received' AND version = 1)");
        DB::statement("CREATE POLICY support_requests_admin_read ON support_requests FOR SELECT USING (current_setting('app.support_requests_access', true) = 'admin')");
        DB::statement("CREATE POLICY support_requests_admin_update ON support_requests FOR UPDATE USING (current_setting('app.support_requests_access', true) = 'admin') WITH CHECK (current_setting('app.support_requests_access', true) = 'admin')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
