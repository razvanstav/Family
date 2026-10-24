<?php

namespace App\Modules\Tasks\Http;

use App\Modules\Tasks\Application\TaskAccess;
use App\Modules\Tasks\Application\TaskCalendar;
use App\Modules\Tasks\Application\TaskRecurrence;
use App\Platform\Audit\Audit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class TaskController
{
    public function __construct(private readonly TaskCalendar $calendar, private readonly TaskRecurrence $recurrence, private readonly TaskAccess $access) {}

    private function query(Request $request, string $family): Builder
    {
        return $this->access->query($family, $request->user()->id);
    }

    private function rows(Builder $query): Builder
    {
        return $this->access->rows($query);
    }

    private function canComplete(Request $request, object $row): bool
    {
        $membership = $request->attributes->get('membership');

        return $this->access->canComplete($row, $request->user()->id, $membership->id, $membership->role);
    }

    private function payload(Request $request, object $row): array
    {
        $membership = $request->attributes->get('membership');

        return $this->access->payload($row, $request->user()->id, $membership->id, $membership->role);
    }

    private function assignees(string $family): Builder
    {
        return DB::table('family_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.family_id', $family)->whereNull('m.revoked_at');
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['open', 'completed'])],
            'visibility' => ['sometimes', Rule::in(['family', 'private'])], 'page' => 'sometimes|integer|min:1|max:100000',
            'assignee_membership_id' => 'sometimes|uuid']);
        $query = $this->query($request, $family)->where('t.visibility', $data['visibility'] ?? 'family');
        if (isset($data['assignee_membership_id'])) {
            $query->where('t.assignee_membership_id', $data['assignee_membership_id']);
        }
        if (($data['status'] ?? 'open') === 'completed') {
            $query->whereNotNull('t.completed_at')->orderByDesc('t.completed_at');
        } else {
            $query->whereNull('t.completed_at')->orderByRaw('t.due_on ASC NULLS LAST');
        }
        $page = (int) ($data['page'] ?? 1);
        $items = $this->rows($query)->orderByDesc('t.created_at')->orderByDesc('t.id')->offset(($page - 1) * 50)->limit(51)->get();

        return response()->json(['items' => $items->take(50)->map(fn (object $row) => $this->payload($request, $row))->values(),
            'next_page' => $items->count() > 50 ? $page + 1 : null,
            'assignees' => $this->assignees($family)->orderBy('u.name')->orderBy('m.id')->get(['m.id', 'u.name'])]);
    }

    public function show(Request $request, string $family, string $task): JsonResponse
    {
        $row = $this->rows($this->query($request, $family)->where('t.id', $task))->firstOrFail();

        return response()->json($this->payload($request, $row));
    }

    private function writable(Request $request): void
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Poți consulta sarcinile și finaliza sarcinile atribuite ție.');
    }

    private function content(Request $request, string $family, ?object $existing = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:160', 'assignee_membership_id' => ['sometimes', 'required', 'uuid'],
            'due_on' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2099-12-31',
            'visibility' => [$existing ? 'required' : 'sometimes', Rule::in(['family', 'private'])],
            'recurrence' => ['sometimes', 'nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_anchor_on' => 'prohibited', 'recurrence_parent_id' => 'prohibited',
            'recurrence_notice' => 'prohibited', 'next_due_on' => 'prohibited',
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'version' => 'prohibited', 'completed_at' => 'prohibited',
        ], ['title.required' => 'Introdu titlul sarcinii.', 'title.max' => 'Titlul poate avea cel mult 160 de caractere.']);
        $assignee = $data['assignee_membership_id'] ?? $request->attributes->get('membership')->id;
        $visibility = $data['visibility'] ?? 'family';
        if ($visibility === 'private' && $assignee !== $request->attributes->get('membership')->id) {
            throw ValidationException::withMessages(['assignee_membership_id' => 'O sarcină personală îți poate fi atribuită numai ție.']);
        }
        if ($assignee !== ($existing->assignee_membership_id ?? null)
            && ! $this->assignees($family)->where('m.id', $assignee)->exists()) {
            throw ValidationException::withMessages(['assignee_membership_id' => 'Alege un membru activ al familiei cu cont.']);
        }
        $recurrence = array_key_exists('recurrence', $data) ? $data['recurrence'] : ($existing->recurrence ?? null);
        $dueOn = $data['due_on'] ?? null;
        if ($recurrence !== null && $dueOn === null) {
            throw ValidationException::withMessages(['due_on' => 'Alege un termen pentru sarcina care se repetă.']);
        }
        $anchorOn = $recurrence === null ? null : (
            $existing && $existing->recurrence === $recurrence && $existing->due_on === $dueOn
                ? $existing->recurrence_anchor_on : $dueOn
        );

        return ['title' => trim($data['title']), 'assignee_membership_id' => $assignee,
            'due_on' => $dueOn, 'visibility' => $visibility, 'recurrence' => $recurrence, 'recurrence_anchor_on' => $anchorOn];
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->writable($request);
        $data = $this->content($request, $family);
        $id = (string) Str::uuid();
        DB::table('family_tasks')->insert($data + ['id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->calendar->syncTask($family, $id);
        Audit::record($family, $request->user()->id, 'task.created', $id);

        return $this->show($request, $family, $id)->setStatusCode(201);
    }

    private function version(Request $request, object $row): void
    {
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        abort_unless($request->header('If-Match') === '"'.$row->version.'"', 412, 'Sarcina s-a schimbat. Reîncarcă înainte de a salva.');
    }

    private function editable(Request $request, string $family, string $task): object
    {
        $row = $this->query($request, $family)->where('t.id', $task)->lockForUpdate()->firstOrFail();
        $this->writable($request);
        abort_unless($row->owner_id === $request->user()->id, 403, 'Numai autorul poate modifica această sarcină.');
        $this->version($request, $row);

        return $row;
    }

    public function update(Request $request, string $family, string $task): JsonResponse
    {
        $row = $this->editable($request, $family, $task);
        $data = $this->content($request, $family, $row);
        DB::table('family_tasks')->where('family_id', $family)->where('id', $task)
            ->update($data + ['version' => $row->version + 1, 'updated_at' => now()]);
        $this->calendar->syncTask($family, $task);
        Audit::record($family, $request->user()->id, 'task.updated', $task);

        return $this->show($request, $family, $task);
    }

    public function completion(Request $request, string $family, string $task): JsonResponse
    {
        $row = $this->query($request, $family)->where('t.id', $task)->lockForUpdate()->firstOrFail();
        abort_unless($this->canComplete($request, $row), 403, 'Numai autorul sau responsabilul poate finaliza această sarcină.');
        $this->version($request, $row);
        $data = $request->validate(['completed' => 'required|boolean']);
        DB::table('family_tasks')->where('family_id', $family)->where('id', $task)
            ->update(['completed_at' => $data['completed'] ? now() : null, 'version' => $row->version + 1, 'updated_at' => now()]);
        $this->calendar->syncTask($family, $task);
        Audit::record($family, $request->user()->id, $data['completed'] ? 'task.completed' : 'task.reopened', $task);

        $outcome = $data['completed'] && $row->completed_at === null && $row->recurrence !== null
            ? $this->recurrence->createNext($family, $row, $request->user()->id) : [];

        $updated = $this->rows($this->query($request, $family)->where('t.id', $task))->firstOrFail();

        return response()->json(array_merge($this->payload($request, $updated), $outcome));
    }

    public function destroy(Request $request, string $family, string $task): Response
    {
        $row = $this->editable($request, $family, $task);
        DB::table('family_tasks')->where('family_id', $family)->where('id', $task)
            ->update(['deleted_at' => now(), 'version' => $row->version + 1, 'updated_at' => now()]);
        $this->calendar->syncTask($family, $task);
        Audit::record($family, $request->user()->id, 'task.deleted', $task);

        return response()->noContent();
    }
}
