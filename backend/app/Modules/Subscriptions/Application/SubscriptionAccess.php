<?php

namespace App\Modules\Subscriptions\Application;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SubscriptionAccess
{
    public function query(string $family, int $actor, bool $pending = true): Builder
    {
        return DB::table('subscriptions as s')->join('users as author', 'author.id', '=', 's.owner_id')
            ->leftJoin('subscription_shares as invite', function ($join) use ($actor) {
                $join->on('invite.subscription_id', '=', 's.id')->where('invite.recipient_id', $actor)
                    ->whereIn('invite.status', ['pending', 'accepted']);
            })
            ->whereNull('s.deleted_at')
            ->whereExists(fn (Builder $owner) => $owner->selectRaw('1')->from('family_memberships as owner_member')
                ->whereColumn('owner_member.family_id', 's.family_id')->whereColumn('owner_member.user_id', 's.owner_id')->whereNull('owner_member.revoked_at'))
            ->where(fn (Builder $access) => $access->where('s.family_id', $family)->orWhereIn('invite.status', $pending ? ['pending', 'accepted'] : ['accepted']));
    }

    public function rows(Builder $query): Builder
    {
        return $query->select('s.id', 's.family_id', 's.owner_id', 's.service_key', 's.service_name', 's.website_url',
            's.amount_minor', 's.currency', 's.recurrence', 's.next_due_on', 's.note', 's.version', 'author.name as owner_name', 'invite.status as invite_status', 'invite.expires_at as invitation_expires_at')
            ->selectRaw('(s.credentials_ciphertext IS NOT NULL) AS has_credentials');
    }

    /** @return array<string, mixed> */
    public function payload(object $row, string $family, int $actor, string $role): array
    {
        $access = $row->family_id === $family ? ($row->owner_id === $actor ? 'owner' : 'family')
            : ($row->invite_status === 'accepted' ? 'shared' : 'invited');
        $pending = $access === 'invited';

        return ['id' => $row->id, 'service_key' => $row->service_key, 'service_name' => $row->service_name,
            'website_url' => $pending ? null : $row->website_url, 'amount_minor' => $pending || $row->amount_minor === null ? null : (int) $row->amount_minor,
            'currency' => $pending ? null : $row->currency, 'recurrence' => $pending ? null : $row->recurrence,
            'next_due_on' => $pending ? null : $row->next_due_on, 'note' => $pending ? null : $row->note,
            'owner_name' => $row->owner_name, 'version' => (int) $row->version, 'access' => $access,
            'invitation_expires_at' => $pending && $row->invitation_expires_at !== null ? (new DateTimeImmutable($row->invitation_expires_at))->format(DATE_ATOM) : null,
            'can_edit' => $access === 'owner' && $role !== 'limited', 'has_credentials' => ! $pending && (bool) $row->has_credentials];
    }
}
