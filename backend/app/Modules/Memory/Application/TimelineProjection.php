<?php

namespace App\Modules\Memory\Application;

use App\Modules\Kitchen\Contracts\MealReferences;
use App\Platform\Contracts\EventHandler;
use App\Platform\Contracts\StoredEvent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TimelineProjection implements EventHandler
{
    public function __construct(private readonly MealReferences $meals) {}

    public function name(): string
    {
        return 'memory.timeline.v1';
    }

    public function events(): array
    {
        return ['memory.moment.changed', 'kitchen.meal.changed'];
    }

    private function assertContext(string $family): void
    {
        if (DB::transactionLevel() < 1 || DB::selectOne("SELECT current_setting('app.family_id', true) as family_id")->family_id !== $family) {
            throw new LogicException('The timeline requires the tenant transaction.');
        }
    }

    private function moments(string $family): Builder
    {
        return DB::table('memory_moments')->where('family_id', $family)
            ->select(['id', 'version as source_version', 'occurred_on', DB::raw('archived_at IS NOT NULL AS removed')]);
    }

    public function handle(StoredEvent $event): void
    {
        $this->assertContext($event->familyId);
        if ($event->schemaVersion !== 1 || ! in_array($event->name, $this->events(), true) || $event->name !== $event->sourceKind.'.changed') {
            throw new LogicException('Unsupported timeline event.');
        }
        $source = $event->sourceKind === 'memory.moment'
            ? $this->moments($event->familyId)->where('id', $event->sourceId)->first()
            : $this->meals->find($event->familyId, $event->sourceId);
        $source = $source ? (array) $source : null;
        if ($source && $source['source_version'] < $event->sourceVersion) {
            throw new LogicException('Canonical source is behind the event.');
        }
        $version = $source['source_version'] ?? $event->sourceVersion;
        $date = $source['occurred_on'] ?? substr($event->occurredAt, 0, 10);
        $removed = ! $source || $source['removed'];
        $previous = DB::table('memory_timeline_sources')->where('family_id', $event->familyId)
            ->where('source_kind', $event->sourceKind)->where('source_id', $event->sourceId)->first();
        if ($previous && $previous->source_version > $version) {
            if (! $removed) {
                return;
            }
            $version = $previous->source_version;
            $date = $previous->occurred_on;
        }
        $this->write($event->familyId, $event->sourceKind, [['id' => $event->sourceId, 'source_version' => $version, 'occurred_on' => $date, 'removed' => $removed]]);
    }

    private function write(string $family, string $kind, array $sources): void
    {
        if ($sources === []) {
            return;
        }
        $rows = array_map(fn (array $source): array => ['family_id' => $family, 'source_kind' => $kind,
            'source_id' => $source['id'], 'source_version' => $source['source_version'], 'occurred_on' => $source['occurred_on'], 'removed' => $source['removed']], $sources);
        DB::table('memory_timeline_sources')->upsert($rows, ['family_id', 'source_kind', 'source_id'], ['source_version', 'occurred_on', 'removed']);
    }

    public function rebuild(string $family): int
    {
        $this->assertContext($family);
        DB::table('memory_timeline_sources')->where('family_id', $family)->delete();
        $count = 0;
        $consume = function (string $kind, array $rows) use ($family, &$count): void {
            $this->write($family, $kind, $rows);
            $count += count($rows);
        };
        $this->moments($family)->chunkById(100, fn ($rows) => $consume('memory.moment', $rows->map(fn (object $row): array => (array) $row)->all()));
        $this->meals->each($family, fn (array $rows) => $consume('kitchen.meal', $rows));

        return $count;
    }
}
