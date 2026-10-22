<?php

namespace App\Modules\Kitchen\Application;

use App\Platform\Contracts\EventHandler;
use App\Platform\Contracts\StoredEvent;
use Illuminate\Support\Facades\DB;
use LogicException;

final class KitchenActivity implements EventHandler
{
    private const SOURCES = ['kitchen.recipe' => ['recipes', 'revision'], 'kitchen.meal' => ['kitchen_meals', 'activity_revision']];

    public function name(): string
    {
        return 'kitchen.activity.v1';
    }

    public function events(): array
    {
        return ['kitchen.recipe.changed', 'kitchen.meal.changed'];
    }

    public function handle(StoredEvent $event): void
    {
        $this->assertContext($event->familyId);
        if ($event->schemaVersion !== 1 || ! isset(self::SOURCES[$event->sourceKind]) || $event->name !== $event->sourceKind.'.changed') {
            throw new LogicException('Unsupported Kitchen event.');
        }
        [$table, $version] = self::SOURCES[$event->sourceKind];
        $source = DB::table($table)->where('family_id', $event->familyId)->where('id', $event->sourceId)
            ->first(['id', $version.' as source_version', 'created_at', 'archived_at']);
        if ($source && $source->source_version < $event->sourceVersion) {
            throw new LogicException('Canonical source is behind the event.');
        }
        $this->reconcile($event->familyId, $event->sourceKind, $event->sourceId, $source?->source_version ?? $event->sourceVersion,
            $source?->created_at ?? $event->occurredAt, ! $source || $source->archived_at !== null);
    }

    private function assertContext(string $familyId): void
    {
        if (DB::transactionLevel() < 1 || DB::selectOne("SELECT current_setting('app.family_id', true) as family_id")->family_id !== $familyId) {
            throw new LogicException('A Kitchen projection requires the tenant transaction.');
        }
    }

    private function reconcile(string $familyId, string $kind, string $id, int $version, string $occurredAt, bool $removed): void
    {
        $query = DB::table('kitchen_activity_sources')->where('family_id', $familyId)->where('source_kind', $kind)->where('source_id', $id);
        $previous = (clone $query)->first();
        if ($previous && $previous->source_version > $version) {
            if (! $removed) {
                return;
            }
            $version = $previous->source_version;
            $occurredAt = $previous->occurred_at;
        }
        DB::table('kitchen_activity_sources')->upsert([['family_id' => $familyId, 'source_kind' => $kind,
            'source_id' => $id, 'source_version' => $version, 'occurred_at' => $occurredAt, 'removed' => $removed]],
            ['family_id', 'source_kind', 'source_id'], ['source_version', 'occurred_at', 'removed']);
    }

    public function rebuild(string $familyId): int
    {
        $this->assertContext($familyId);
        DB::table('kitchen_activity_sources')->where('family_id', $familyId)->delete();
        $count = 0;
        foreach (self::SOURCES as $kind => [$table, $version]) {
            DB::table($table)->where('family_id', $familyId)->orderBy('id')->chunk(100, function ($sources) use ($familyId, $kind, $version, &$count): void {
                $rows = $sources->map(fn (object $source) => ['family_id' => $familyId, 'source_kind' => $kind, 'source_id' => $source->id,
                    'source_version' => $source->{$version}, 'occurred_at' => $source->created_at, 'removed' => $source->archived_at !== null])->all();
                DB::table('kitchen_activity_sources')->insert($rows);
                $count += count($rows);
            });
        }

        return $count;
    }
}
