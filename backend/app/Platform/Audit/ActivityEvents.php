<?php

namespace App\Platform\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ActivityEvents
{
    /** @param list<string> $actions */
    public static function query(string $family, array $actions, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return DB::table('audit_events as activity')->join('users as activity_actor', 'activity_actor.id', '=', 'activity.actor_id')
            ->where('activity.family_id', $family)->whereIn('activity.action', $actions)
            ->where('activity.created_at', '>=', $from->utc()->toDateTimeString())
            ->where('activity.created_at', '<', $until->utc()->toDateTimeString());
    }

    /**
     * The caller must join its currently authorized source and select its title.
     *
     * @param  callable(object):array<string,mixed>  $target
     * @return list<array{id:string,action:string,actor_name:string,occurred_at:string,title:string,target:array<string,mixed>}>
     */
    public static function read(Builder $query, int $limit, callable $target): array
    {
        return $query->addSelect('activity.id', 'activity.action', 'activity.resource_id', 'activity.created_at', 'activity_actor.name as actor_name')
            ->orderByDesc('activity.created_at')->orderByDesc('activity.id')->limit($limit)->get()
            ->map(fn (object $row): array => ['id' => (string) $row->id, 'action' => $row->action, 'actor_name' => $row->actor_name,
                'occurred_at' => CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String(), 'title' => (string) $row->title,
                'target' => $target($row)])->all();
    }
}
