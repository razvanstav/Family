<?php

namespace App\Platform\Families;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MemberProfileAccess
{
    public function subject(string $family, string $member): object
    {
        return DB::table('family_memberships as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('member_profiles as p', function ($join) {
                $join->on('p.family_id', '=', 'm.family_id')->on('p.membership_id', '=', 'm.id');
            })->leftJoin('media_assets as photo', function ($join) {
                $join->on('photo.family_id', '=', 'm.family_id')->on('photo.id', '=', 'p.photo_asset_id');
            })->where('m.family_id', $family)->where('m.id', $member)->whereNull('m.revoked_at')
            ->select('m.id', 'm.user_id', 'm.role', 'p.birth_date', 'p.birthday_shared', 'p.birthday_version', 'p.photo_asset_id', 'photo.status as photo_status')
            ->selectRaw('coalesce(u.name, m.child_name) as name')
            ->selectRaw('coalesce(p.version, 0) as version')->firstOrFail();
    }

    public function age(?string $birthDate): ?int
    {
        return $birthDate === null ? null : (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable(now('Europe/Bucharest')->toDateString()))->y;
    }

    public function canWrite(Request $request, string $family, object $subject): bool
    {
        $age = $this->age($subject->birth_date);
        if ((int) $subject->user_id === $request->user()->id) {
            return $age === null || $age >= 14;
        }
        if ($age === null || $age >= 18 || $request->attributes->get('membership')->role === 'limited') {
            return false;
        }

        return DB::table('member_profile_parents as p')->join('family_memberships as m', function ($join) {
            $join->on('m.family_id', '=', 'p.family_id')->on('m.id', '=', 'p.parent_membership_id');
        })->where('p.family_id', $family)->where('p.membership_id', $subject->id)
            ->where('m.user_id', $request->user()->id)->whereNull('m.revoked_at')->exists();
    }

    public function version(Request $request, int $version): void
    {
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        abort_unless($request->header('If-Match') === '"'.$version.'"', 412, 'Datele s-au schimbat. Reîncarcă înainte de a salva.');
    }

    /** @param list<string> $parents */
    public function validateParents(string $family, string $member, array $parents): void
    {
        if ($parents === []) {
            return;
        }
        $adults = DB::table('family_memberships as m')->leftJoin('member_profiles as p', function ($join) {
            $join->on('p.family_id', '=', 'm.family_id')->on('p.membership_id', '=', 'm.id');
        })->where('m.family_id', $family)->whereIn('m.id', $parents)->where('m.id', '!=', $member)->whereNotNull('m.user_id')
            ->whereNull('m.revoked_at')->whereIn('m.role', ['owner', 'adult'])->get(['m.id', 'p.birth_date']);
        if ($adults->count() !== count($parents) || $adults->contains(fn ($parent) => $parent->birth_date !== null && $this->age($parent->birth_date) < 18)) {
            throw ValidationException::withMessages(['parent_ids' => 'Alege părinți adulți, activi, din această familie.']);
        }
    }
}
