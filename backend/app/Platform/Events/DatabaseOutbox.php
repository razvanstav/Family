<?php

namespace App\Platform\Events;

use App\Platform\Contracts\EventHandler;
use App\Platform\Contracts\Outbox;
use App\Platform\Contracts\StoredEvent;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class DatabaseOutbox implements Outbox
{
    /** @var array<string, EventHandler> */
    private array $handlers = [];

    /** @param list<EventHandler> $handlers */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            if (isset($this->handlers[$handler->name()])) {
                throw new LogicException('Duplicate outbox handler.');
            }
            $this->handlers[$handler->name()] = $handler;
        }
    }

    public function append(string $familyId, string $name, string $sourceKind, string $sourceId, int $sourceVersion): string
    {
        $context = DB::selectOne("SELECT current_setting('app.family_id', true) as family_id");
        if (DB::transactionLevel() < 1 || $context->family_id !== $familyId || ! Str::isUuid($sourceId) || $sourceVersion < 1) {
            throw new LogicException('An outbox event requires a valid tenant transaction and source.');
        }
        $targets = array_filter($this->handlers, fn (EventHandler $handler) => in_array($name, $handler->events(), true));
        if ($targets === []) {
            throw new LogicException('No handler registered for the event.');
        }
        $existing = DB::table('outbox_events')->where('family_id', $familyId)->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)->where('source_version', $sourceVersion)->first();
        if ($existing) {
            if ($existing->name !== $name || $existing->schema_version !== 1) {
                throw new LogicException('The source version already has a different event.');
            }

            return $existing->id;
        }
        $id = (string) Str::uuid();
        DB::table('outbox_events')->insert([
            'id' => $id, 'family_id' => $familyId, 'name' => $name, 'schema_version' => 1,
            'source_kind' => $sourceKind, 'source_id' => $sourceId, 'source_version' => $sourceVersion, 'occurred_at' => now(),
        ]);
        foreach ($targets as $handler) {
            DB::table('outbox_deliveries')->insert(['family_id' => $familyId, 'event_id' => $id,
                'handler' => $handler->name(), 'available_at' => now()]);
        }

        return $id;
    }

    public function inFamily(string $familyId, Closure $callback): mixed
    {
        if (! Str::isUuid($familyId) || DB::transactionLevel() !== 0) {
            throw new LogicException('An independent transaction and a valid family are required.');
        }

        return DB::transaction(function () use ($familyId, $callback) {
            DB::table('families')->where('id', $familyId)->lockForUpdate()->firstOrFail();
            DB::select("SELECT set_config('app.family_id', ?, true)", [$familyId]);

            return $callback();
        });
    }

    /** @return array{delivered:int,failed:int} */
    public function deliverFamily(string $familyId, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000 || DB::transactionLevel() !== 0) {
            throw new LogicException('Dispatch requires a bounded batch outside a transaction.');
        }
        $pending = $this->inFamily($familyId, fn () => DB::table('outbox_deliveries')->where('family_id', $familyId)
            ->whereNull('delivered_at')->where('available_at', '<=', now())->orderBy('available_at')->orderBy('event_id')->orderBy('handler')->limit($limit)->get(['event_id', 'handler']));
        $counts = ['delivered' => 0, 'failed' => 0];
        foreach ($pending as $delivery) {
            try {
                $delivered = $this->inFamily($familyId, function () use ($familyId, $delivery) {
                    $receipt = DB::table('outbox_deliveries')->where('family_id', $familyId)->where('event_id', $delivery->event_id)
                        ->where('handler', $delivery->handler)->lockForUpdate()->firstOrFail();
                    if ($receipt->delivered_at !== null || $receipt->available_at > now()->toDateTimeString()) {
                        return false;
                    }
                    $row = DB::table('outbox_events')->where('family_id', $familyId)->where('id', $delivery->event_id)->firstOrFail();
                    $handler = $this->handlers[$receipt->handler] ?? throw new LogicException('Unknown outbox handler.');
                    if (! in_array($row->name, $handler->events(), true) || $row->schema_version !== 1) {
                        throw new LogicException('Unsupported event schema.');
                    }
                    $handler->handle(new StoredEvent($row->id, $row->family_id, $row->name, $row->schema_version, $row->source_kind, $row->source_id, $row->source_version, $row->occurred_at));
                    DB::table('outbox_deliveries')->where('family_id', $familyId)->where('event_id', $receipt->event_id)->where('handler', $receipt->handler)
                        ->update(['delivered_at' => now(), 'attempts' => $receipt->attempts + 1, 'last_error' => null]);

                    return true;
                });
                $counts['delivered'] += (int) $delivered;
            } catch (Throwable) {
                $this->inFamily($familyId, function () use ($familyId, $delivery) {
                    $query = DB::table('outbox_deliveries')->where('family_id', $familyId)->where('event_id', $delivery->event_id)->where('handler', $delivery->handler);
                    $receipt = (clone $query)->lockForUpdate()->firstOrFail();
                    if ($receipt->delivered_at === null) {
                        $attempt = $receipt->attempts + 1;
                        $query->update(['attempts' => $attempt, 'available_at' => now()->addSeconds(min(3600, 15 * 2 ** min($attempt, 8))), 'last_error' => 'handler_failed']);
                    }
                });
                $counts['failed']++;
            }
        }

        return $counts;
    }

    public function replayFamily(string $familyId, string $handlerName): int
    {
        $handler = $this->handlers[$handlerName] ?? throw new LogicException('Unknown outbox handler.');

        return $this->inFamily($familyId, function () use ($familyId, $handler) {
            $count = 0;
            DB::table('outbox_events')->where('family_id', $familyId)->whereIn('name', $handler->events())->orderBy('id')
                ->chunk(100, function ($events) use ($handler, &$count): void {
                    foreach ($events as $event) {
                        DB::table('outbox_deliveries')->upsert([['family_id' => $event->family_id, 'event_id' => $event->id,
                            'handler' => $handler->name(), 'attempts' => 0, 'available_at' => now(), 'delivered_at' => null, 'last_error' => null]],
                            ['event_id', 'handler'], ['attempts', 'available_at', 'delivered_at', 'last_error']);
                        $count++;
                    }
                });

            return $count;
        });
    }
}
