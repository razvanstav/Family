<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->boolean('birthday_shared')->default(false);
            $table->unsignedInteger('birthday_version')->default(1);
        });
        Schema::table('calendar_reminder_recipients', function (Blueprint $table) {
            $table->unsignedInteger('offset_minutes')->nullable();
        });
        Schema::create('birthday_reminder_preferences', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('subject_membership_id');
            $table->uuid('recipient_membership_id');
            $table->unsignedInteger('offset_minutes');
            $table->unsignedInteger('version')->default(1);
            $table->primary(['subject_membership_id', 'recipient_membership_id']);
            $table->foreign(['family_id', 'subject_membership_id'])->references(['family_id', 'membership_id'])->on('member_profiles')->cascadeOnDelete();
            $table->foreign(['family_id', 'recipient_membership_id'])->references(['family_id', 'id'])->on('family_memberships');
        });
        DB::statement('ALTER TABLE birthday_reminder_preferences ADD CONSTRAINT birthday_offset CHECK (offset_minutes IN (0,1440,10080))');
        DB::statement('ALTER TABLE birthday_reminder_preferences ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE birthday_reminder_preferences FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_boundary ON birthday_reminder_preferences USING (family_id = nullif(current_setting('app.family_id', true), '')::uuid) WITH CHECK (family_id = nullif(current_setting('app.family_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM calendar_items WHERE source_type = 'birthday'");
        Schema::dropIfExists('birthday_reminder_preferences');
        Schema::table('calendar_reminder_recipients', fn (Blueprint $table) => $table->dropColumn('offset_minutes'));
        Schema::table('member_profiles', fn (Blueprint $table) => $table->dropColumn(['birthday_shared', 'birthday_version']));
    }
};
