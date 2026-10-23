<?php

namespace App\Platform\Families;

use App\Modules\Calendar\Contracts\BirthdaySource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class FamilyBirthdaySource implements BirthdaySource
{
    public function type(): string
    {
        return 'birthday';
    }

    private function profiles(string $family): Builder
    {
        return DB::table('member_profiles as p')->join('family_memberships as m', function ($join) {
            $join->on('m.id', '=', 'p.membership_id')->on('m.family_id', '=', 'p.family_id');
        })->where('p.family_id', $family)->where('p.birthday_shared', true)->whereNotNull('p.birth_date')->whereNull('m.revoked_at');
    }

    public function references(string $family): Builder
    {
        return $this->profiles($family)->crossJoin(DB::raw('generate_series(1900, 2100) as birthday_year(year)'))
            ->whereRaw('birthday_year.year >= extract(year from p.birth_date)')
            ->selectRaw("md5('family-birthday:' || m.id::text || ':' || birthday_year.year::text)::uuid as id, m.id as parent_id, p.birthday_version as version, true as active");
    }

    /** @return list<array{type: string, id: string, parent_id: string, version: int, owner_id: int, title: string, date: string, active: bool}> */
    public function occurrences(string $family, int $year): array
    {
        return $this->profiles($family)->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->join('family_memberships as owner', function ($join) {
                $join->on('owner.family_id', '=', 'p.family_id')->where('owner.role', 'owner')->whereNull('owner.revoked_at');
            })->whereRaw('extract(year from p.birth_date) <= ?', [$year])
            ->select('m.id', 'p.birthday_version as version', 'p.birth_date', 'owner.user_id as owner_id')
            ->selectRaw("coalesce(u.name, m.child_name) as name, md5('family-birthday:' || m.id::text || ':' || ?::text)::uuid as source_id", [$year])
            ->orderBy('m.id')->get()->map(function (object $row) use ($year): array {
                $first = CarbonImmutable::create($year, (int) substr($row->birth_date, 5, 2), 1, 0, 0, 0, 'Europe/Bucharest');
                $date = $first->day(min((int) substr($row->birth_date, 8, 2), $first->daysInMonth));

                return ['type' => $this->type(), 'id' => $row->source_id, 'parent_id' => $row->id, 'version' => (int) $row->version,
                    'owner_id' => $row->owner_id, 'title' => 'Ziua de naștere · '.$row->name, 'date' => $date->toDateString(), 'active' => true];
            })->all();
    }
}
