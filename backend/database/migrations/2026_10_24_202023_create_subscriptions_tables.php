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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('service_key', 64);
            $table->string('service_name', 120);
            $table->string('website_url', 2048)->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('recurrence', 12)->default('monthly');
            $table->date('next_due_on')->nullable();
            $table->text('note')->nullable();
            $table->text('credentials_ciphertext')->nullable();
            $table->integer('version')->default(1);
            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();
            $table->unique(['family_id', 'id']);
            $table->index(['family_id', 'deleted_at', 'next_due_on']);
        });
        Schema::create('subscription_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('subscription_id');
            $table->foreignId('recipient_id')->constrained('users');
            $table->string('status', 12)->default('pending');
            $table->string('token_hash', 64)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->unique(['subscription_id', 'recipient_id']);
            $table->foreign(['family_id', 'subscription_id'])->references(['family_id', 'id'])->on('subscriptions');
            $table->index(['recipient_id', 'status']);
        });
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscription_amount CHECK (amount_minor IS NULL OR amount_minor BETWEEN 0 AND 99999999)');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscription_currency CHECK (currency IN ('RON', 'EUR', 'USD'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscription_recurrence CHECK (recurrence IN ('daily', 'weekly', 'monthly', 'yearly'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscription_date CHECK (next_due_on IS NULL OR next_due_on BETWEEN '1900-01-01' AND '2100-12-31')");
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscription_version CHECK (version > 0)');
        DB::statement("ALTER TABLE subscription_shares ADD CONSTRAINT subscription_share_status CHECK (status IN ('pending', 'accepted', 'declined', 'revoked'))");
        DB::statement("ALTER TABLE subscription_shares ADD CONSTRAINT subscription_share_token CHECK ((status = 'pending' AND token_hash IS NOT NULL AND expires_at IS NOT NULL) OR (status <> 'pending' AND token_hash IS NULL AND expires_at IS NULL))");
        $actor = "nullif(current_setting('app.actor_id', true), '')::bigint";
        $family = "nullif(current_setting('app.family_id', true), '')::uuid";
        $ownerActive = 'EXISTS (SELECT 1 FROM family_memberships owner_member WHERE owner_member.family_id = subscriptions.family_id AND owner_member.user_id = subscriptions.owner_id AND owner_member.revoked_at IS NULL)';
        $familyAccess = "family_id = {$family} AND EXISTS (SELECT 1 FROM family_memberships viewer WHERE viewer.family_id = subscriptions.family_id AND viewer.user_id = {$actor} AND viewer.revoked_at IS NULL)";
        $owner = "family_id = {$family} AND owner_id = {$actor} AND {$ownerActive}";
        foreach (['subscriptions', 'subscription_shares'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }
        DB::statement("CREATE POLICY subscription_read ON subscriptions FOR SELECT USING ({$ownerActive} AND (({$familyAccess}) OR EXISTS (SELECT 1 FROM subscription_shares share WHERE share.subscription_id = subscriptions.id AND share.recipient_id = {$actor} AND share.status IN ('pending', 'accepted'))))");
        DB::statement("CREATE POLICY subscription_insert ON subscriptions FOR INSERT WITH CHECK ({$owner})");
        $recipient = "{$ownerActive} AND EXISTS (SELECT 1 FROM subscription_shares share WHERE share.subscription_id = subscriptions.id AND share.recipient_id = {$actor} AND share.status IN ('pending', 'accepted'))";
        DB::statement("CREATE POLICY subscription_update ON subscriptions FOR UPDATE USING (({$owner}) OR ({$recipient})) WITH CHECK (({$owner}) OR ({$recipient}))");
        DB::statement("CREATE POLICY subscription_delete ON subscriptions FOR DELETE USING ({$owner})");
        DB::statement("CREATE POLICY subscription_share_read ON subscription_shares FOR SELECT USING (family_id = {$family} OR recipient_id = {$actor})");
        $shareOwner = "family_id = {$family} AND EXISTS (SELECT 1 FROM subscriptions parent WHERE parent.id = subscription_shares.subscription_id AND parent.family_id = subscription_shares.family_id AND parent.owner_id = {$actor})";
        DB::statement("CREATE POLICY subscription_share_insert ON subscription_shares FOR INSERT WITH CHECK ({$shareOwner})");
        DB::statement("CREATE POLICY subscription_share_update ON subscription_shares FOR UPDATE USING (({$shareOwner}) OR recipient_id = {$actor}) WITH CHECK (({$shareOwner}) OR recipient_id = {$actor})");
        DB::statement("CREATE POLICY subscription_share_delete ON subscription_shares FOR DELETE USING ({$shareOwner})");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION subscription_recipient_update_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF nullif(current_setting('app.actor_id', true), '')::bigint IS DISTINCT FROM OLD.owner_id THEN
        IF (to_jsonb(NEW) - ARRAY['version', 'updated_at']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['version', 'updated_at'])
            OR NEW.version <> OLD.version + 1 THEN
            RAISE EXCEPTION 'Subscription recipients cannot change its content' USING ERRCODE = '42501';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER subscription_recipient_update_guard BEFORE UPDATE ON subscriptions FOR EACH ROW EXECUTE FUNCTION subscription_recipient_update_guard();
CREATE FUNCTION subscription_share_update_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF ROW(NEW.id, NEW.family_id, NEW.subscription_id, NEW.recipient_id, NEW.created_at)
        IS DISTINCT FROM ROW(OLD.id, OLD.family_id, OLD.subscription_id, OLD.recipient_id, OLD.created_at) THEN
        RAISE EXCEPTION 'Subscription share identity cannot change' USING ERRCODE = '42501';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM subscriptions parent WHERE parent.id = OLD.subscription_id AND parent.owner_id = nullif(current_setting('app.actor_id', true), '')::bigint) THEN
        IF OLD.recipient_id IS DISTINCT FROM nullif(current_setting('app.actor_id', true), '')::bigint
            OR OLD.status <> 'pending' OR NEW.status NOT IN ('accepted', 'declined') THEN
            RAISE EXCEPTION 'Subscription recipients can only answer a pending invitation' USING ERRCODE = '42501';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER subscription_share_update_guard BEFORE UPDATE ON subscription_shares FOR EACH ROW EXECUTE FUNCTION subscription_share_update_guard();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_shares');
        Schema::dropIfExists('subscriptions');
        DB::statement('DROP FUNCTION IF EXISTS subscription_recipient_update_guard()');
        DB::statement('DROP FUNCTION IF EXISTS subscription_share_update_guard()');
    }
};
