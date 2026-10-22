<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE family_memberships ALTER COLUMN user_id DROP NOT NULL');
        DB::statement('ALTER TABLE family_memberships ADD COLUMN child_name varchar(100)');
        DB::statement("ALTER TABLE family_memberships ADD CONSTRAINT membership_identity CHECK ((user_id IS NOT NULL AND child_name IS NULL) OR (user_id IS NULL AND child_name IS NOT NULL AND length(trim(child_name)) > 0 AND role = 'limited'))");
        DB::statement('ALTER TABLE documents DROP CONSTRAINT document_visibility');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT document_visibility CHECK (visibility IN ('private', 'family', 'selected', 'adults'))");
        DB::statement('ALTER TABLE documents ADD COLUMN subject_membership_id uuid');
        DB::statement('ALTER TABLE documents ADD CONSTRAINT document_subject FOREIGN KEY (family_id, subject_membership_id) REFERENCES family_memberships (family_id, id)');
        DB::statement('UPDATE documents AS d SET subject_membership_id = m.id FROM family_memberships AS m WHERE m.family_id = d.family_id AND m.user_id = d.owner_id AND d.category IN (\'personal\', \'health\')');
        $changed = DB::table('documents')->where(fn ($query) => $query
            ->where(fn ($home) => $home->where('category', 'home')->where('visibility', '!=', 'adults'))
            ->orWhere(fn ($memories) => $memories->where('category', 'memories')->where('visibility', '!=', 'family')))->get(['id', 'family_id', 'owner_id']);
        if ($changed->isNotEmpty()) {
            DB::table('documents')->whereIn('id', $changed->pluck('id'))->update(['visibility' => DB::raw("CASE WHEN category = 'home' THEN 'adults' ELSE 'family' END"), 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            DB::table('document_grants')->whereIn('document_id', $changed->pluck('id'))->delete();
            DB::table('families')->whereIn('id', $changed->pluck('family_id')->unique())->increment('authz_revision');
            DB::table('audit_events')->insert($changed->map(fn ($doc) => ['id' => (string) Str::uuid(), 'family_id' => $doc->family_id, 'actor_id' => $doc->owner_id, 'action' => 'document.category_access_applied', 'resource_id' => $doc->id, 'created_at' => now()])->all());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('family_memberships')->whereNull('user_id')->exists() || DB::table('documents')->where('visibility', 'adults')->exists()) {
            throw new LogicException('Rollback indisponibil cât există profiluri fără cont sau documente pentru adulți. Datele trebuie păstrate sau migrate explicit.');
        }
        DB::statement('ALTER TABLE documents DROP CONSTRAINT document_visibility');
        DB::statement('ALTER TABLE documents DROP CONSTRAINT document_subject');
        DB::statement('ALTER TABLE documents DROP COLUMN subject_membership_id');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT document_visibility CHECK (visibility IN ('private', 'family', 'selected'))");
        DB::statement('ALTER TABLE family_memberships DROP CONSTRAINT membership_identity');
        DB::statement('ALTER TABLE family_memberships DROP COLUMN child_name');
        DB::statement('ALTER TABLE family_memberships ALTER COLUMN user_id SET NOT NULL');
    }
};
