<?php

namespace App\Modules\Expenses\Http;

use App\Modules\Expenses\Application\ExpenseAccess;
use App\Modules\Expenses\Application\ExpenseRecurrence;
use App\Platform\Audit\Audit;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ExpenseController
{
    private const CATEGORIES = ['food', 'home', 'transport', 'health', 'children', 'leisure', 'other'];

    public function __construct(private readonly ExpenseAccess $access, private readonly ExpenseRecurrence $recurrence) {}

    private function query(Request $request, string $family): Builder
    {
        return $this->access->query($family, $request->user()->id);
    }

    private function payload(Request $request, object $row): array
    {
        return $this->access->payload($row, $request->user()->id, $request->attributes->get('membership')->role);
    }

    private function payers(string $family): Builder
    {
        return DB::table('family_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.family_id', $family)->whereNull('m.revoked_at');
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $data = $request->validate(['month' => ['sometimes', 'regex:/^(19|20)\d{2}-(0[1-9]|1[0-2])$/'],
            'visibility' => ['sometimes', Rule::in(['family', 'private'])], 'payment_status' => ['sometimes', Rule::in(['paid', 'planned'])],
            'payer_membership_id' => 'sometimes|uuid', 'page' => 'sometimes|integer|min:1|max:100000']);
        $month = $data['month'] ?? now('Europe/Bucharest')->format('Y-m');
        $visibility = $data['visibility'] ?? 'family';
        $status = $data['payment_status'] ?? 'paid';
        $date = new DateTimeImmutable($month.'-01');
        $query = $this->query($request, $family)->where('e.visibility', $visibility)
            ->where('e.payment_status', $status)
            ->where('e.spent_on', '>=', $month.'-01')->where('e.spent_on', '<', $date->modify('+1 month')->format('Y-m-d'));
        $memberTotals = (clone $query)->join('family_memberships as payer', function ($join) {
            $join->on('payer.family_id', '=', 'e.family_id')->on('payer.id', '=', 'e.payer_membership_id');
        })->join('users as paid_by', 'paid_by.id', '=', 'payer.user_id')
            ->select('e.payer_membership_id', 'paid_by.name as payer_name')->selectRaw('SUM(e.amount_bani) AS total_bani, COUNT(*) AS count')
            ->groupBy('e.payer_membership_id', 'paid_by.name')->orderByDesc('total_bani')->orderBy('paid_by.name')->orderBy('e.payer_membership_id')->get()
            ->map(fn (object $row) => ['payer_membership_id' => $row->payer_membership_id, 'payer_name' => $row->payer_name,
                'total_bani' => (int) $row->total_bani, 'count' => (int) $row->count]);
        $payerMembershipId = $data['payer_membership_id'] ?? null;
        if ($payerMembershipId !== null) {
            $query->where('e.payer_membership_id', $payerMembershipId);
        }
        $categories = (clone $query)->select('e.category')->selectRaw('SUM(e.amount_bani) AS total_bani, COUNT(*) AS count')
            ->groupBy('e.category')->orderByDesc('total_bani')->orderBy('e.category')->get()
            ->map(fn (object $row) => ['category' => $row->category, 'total_bani' => (int) $row->total_bani, 'count' => (int) $row->count]);
        $page = (int) ($data['page'] ?? 1);
        $items = $this->access->rows($query)->orderBy('e.spent_on', $status === 'planned' ? 'asc' : 'desc')->orderByDesc('e.created_at')->orderByDesc('e.id')
            ->offset(($page - 1) * 50)->limit(51)->get();

        return response()->json(['month' => $month, 'visibility' => $visibility, 'payment_status' => $status, 'total_bani' => $categories->sum('total_bani'),
            'count' => $categories->sum('count'), 'categories' => $categories, 'payer_membership_id' => $payerMembershipId, 'member_totals' => $memberTotals,
            'items' => $items->take(50)->map(fn (object $row) => $this->payload($request, $row))->values(),
            'next_page' => $items->count() > 50 ? $page + 1 : null,
            'payers' => $this->payers($family)->orderBy('u.name')->orderBy('m.id')->get(['m.id', 'u.name'])]);
    }

    public function show(Request $request, string $family, string $expense): JsonResponse
    {
        $row = $this->access->rows($this->query($request, $family)->where('e.id', $expense))->firstOrFail();

        return response()->json($this->payload($request, $row));
    }

    private function writable(Request $request): void
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Accesul tău permite numai consultarea cheltuielilor.');
    }

    private function content(Request $request, string $family, ?object $existing = null): array
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,5})(?:[.,]\d{1,2})?$/'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'spent_on' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2099-12-31',
            'payer_membership_id' => 'required|uuid', 'note' => 'nullable|string|max:240',
            'visibility' => [$existing ? 'required' : 'sometimes', Rule::in(['family', 'private'])],
            'payment_status' => ['sometimes', Rule::in(['paid', 'planned'])],
            'recurrence' => ['sometimes', 'nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_anchor_on' => 'prohibited', 'recurrence_parent_id' => 'prohibited', 'has_next_payment' => 'prohibited',
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'amount_bani' => 'prohibited', 'version' => 'prohibited',
        ], ['amount.regex' => 'Introdu o sumă între 0,01 și 999.999,99 lei, cu cel mult două zecimale.']);
        [$lei, $bani] = array_pad(explode('.', str_replace(',', '.', $data['amount'])), 2, '');
        $amount = (int) $lei * 100 + (int) str_pad($bani, 2, '0');
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Suma trebuie să fie de cel puțin 0,01 lei.']);
        }
        if ($data['payer_membership_id'] !== ($existing->payer_membership_id ?? null)
            && ! $this->payers($family)->where('m.id', $data['payer_membership_id'])->exists()) {
            throw ValidationException::withMessages(['payer_membership_id' => 'Alege un membru activ al familiei cu cont.']);
        }

        $status = $data['payment_status'] ?? $existing->payment_status ?? 'paid';
        $recurrence = array_key_exists('recurrence', $data) ? $data['recurrence'] : ($existing->recurrence ?? null);
        if ($existing && $status !== $existing->payment_status) {
            throw ValidationException::withMessages(['payment_status' => 'Folosește Marchează plătită pentru o plată planificată.']);
        }
        if ($existing && $recurrence !== $existing->recurrence
            && DB::table('expenses')->where('family_id', $family)->where('recurrence_parent_id', $existing->id)->exists()) {
            throw ValidationException::withMessages(['recurrence' => 'Modifică repetarea în următoarea plată.']);
        }
        $anchor = $recurrence === null ? null : (($existing && $existing->recurrence === $recurrence && $existing->spent_on === $data['spent_on'])
            ? $existing->recurrence_anchor_on : $data['spent_on']);

        return ['amount_bani' => $amount, 'category' => $data['category'], 'spent_on' => $data['spent_on'],
            'payer_membership_id' => $data['payer_membership_id'], 'note' => $data['note'] ?? null,
            'visibility' => $data['visibility'] ?? 'family', 'payment_status' => $status, 'recurrence' => $recurrence, 'recurrence_anchor_on' => $anchor];
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->writable($request);
        $data = $this->content($request, $family);
        $id = (string) Str::uuid();
        DB::table('expenses')->insert($data + ['id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'expense.created', $id);
        $extra = $data['payment_status'] === 'paid' && $data['recurrence'] !== null
            ? $this->recurrence->createNext($family, (object) ($data + ['id' => $id, 'owner_id' => $request->user()->id]), $request->user()->id) : [];

        return $this->withRecurrence($request, $family, $id, $extra)->setStatusCode(201);
    }

    private function editable(Request $request, string $family, string $expense): object
    {
        $row = $this->query($request, $family)->where('e.id', $expense)->lockForUpdate()->firstOrFail();
        $this->writable($request);
        abort_unless($row->owner_id === $request->user()->id, 403, 'Numai autorul poate modifica această cheltuială.');
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        abort_unless($request->header('If-Match') === '"'.$row->version.'"', 412, 'Cheltuiala s-a schimbat. Reîncarcă înainte de a salva.');

        return $row;
    }

    public function update(Request $request, string $family, string $expense): JsonResponse
    {
        $row = $this->editable($request, $family, $expense);
        $data = $this->content($request, $family, $row);
        DB::table('expenses')->where('family_id', $family)->where('id', $expense)->update($data + ['version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'expense.updated', $expense);
        $extra = $data['payment_status'] === 'paid' && $data['recurrence'] !== null
            ? $this->recurrence->createNext($family, (object) array_replace((array) $row, $data), $request->user()->id) : [];

        return $this->withRecurrence($request, $family, $expense, $extra);
    }

    private function withRecurrence(Request $request, string $family, string $expense, array $extra): JsonResponse
    {
        $response = $this->show($request, $family, $expense);

        return $response->setData($response->getData(true) + $extra);
    }

    public function payment(Request $request, string $family, string $expense): JsonResponse
    {
        $row = $this->editable($request, $family, $expense);
        $request->validate(['payment_status' => ['required', Rule::in(['paid'])]]);
        if ($row->payment_status === 'paid') {
            return $this->show($request, $family, $expense);
        }
        DB::table('expenses')->where('family_id', $family)->where('id', $expense)
            ->update(['payment_status' => 'paid', 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'expense.paid', $expense);
        $extra = $row->recurrence !== null ? $this->recurrence->createNext($family, $row, $request->user()->id) : [];

        return $this->withRecurrence($request, $family, $expense, $extra);
    }

    public function destroy(Request $request, string $family, string $expense): Response
    {
        $row = $this->editable($request, $family, $expense);
        DB::table('expenses')->where('family_id', $family)->where('id', $expense)
            ->update(['deleted_at' => now(), 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'expense.deleted', $expense);

        return response()->noContent();
    }
}
