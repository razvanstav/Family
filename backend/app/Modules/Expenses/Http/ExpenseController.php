<?php

namespace App\Modules\Expenses\Http;

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

    private function query(Request $request, string $family): Builder
    {
        return DB::table('expenses as e')->where('e.family_id', $family)->whereNull('e.deleted_at')
            ->where(function (Builder $query) use ($request) {
                $query->where('e.visibility', 'family')->orWhere('e.owner_id', $request->user()->id);
            });
    }

    private function rows(Builder $query): Builder
    {
        return $query->join('users as owner', 'owner.id', '=', 'e.owner_id')
            ->join('family_memberships as payer', function ($join) {
                $join->on('payer.family_id', '=', 'e.family_id')->on('payer.id', '=', 'e.payer_membership_id');
            })->join('users as paid_by', 'paid_by.id', '=', 'payer.user_id')
            ->select('e.*', 'owner.name as owner_name', 'paid_by.name as payer_name');
    }

    private function payload(Request $request, object $row): array
    {
        return ['id' => $row->id, 'amount_bani' => (int) $row->amount_bani, 'category' => $row->category,
            'spent_on' => $row->spent_on, 'note' => $row->note, 'visibility' => $row->visibility,
            'owner_name' => $row->owner_name, 'payer_membership_id' => $row->payer_membership_id,
            'payer_name' => $row->payer_name, 'version' => (int) $row->version,
            'can_edit' => $row->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited'];
    }

    private function payers(string $family): Builder
    {
        return DB::table('family_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.family_id', $family)->whereNull('m.revoked_at');
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $data = $request->validate(['month' => ['sometimes', 'regex:/^(19|20)\d{2}-(0[1-9]|1[0-2])$/'],
            'visibility' => ['sometimes', Rule::in(['family', 'private'])], 'page' => 'sometimes|integer|min:1|max:100000']);
        $month = $data['month'] ?? now('Europe/Bucharest')->format('Y-m');
        $visibility = $data['visibility'] ?? 'family';
        $date = new DateTimeImmutable($month.'-01');
        $query = $this->query($request, $family)->where('e.visibility', $visibility)
            ->where('e.spent_on', '>=', $month.'-01')->where('e.spent_on', '<', $date->modify('+1 month')->format('Y-m-d'));
        $categories = (clone $query)->select('e.category')->selectRaw('SUM(e.amount_bani) AS total_bani, COUNT(*) AS count')
            ->groupBy('e.category')->orderByDesc('total_bani')->orderBy('e.category')->get()
            ->map(fn (object $row) => ['category' => $row->category, 'total_bani' => (int) $row->total_bani, 'count' => (int) $row->count]);
        $page = (int) ($data['page'] ?? 1);
        $items = $this->rows($query)->orderByDesc('e.spent_on')->orderByDesc('e.created_at')->orderByDesc('e.id')
            ->offset(($page - 1) * 50)->limit(51)->get();

        return response()->json(['month' => $month, 'visibility' => $visibility, 'total_bani' => $categories->sum('total_bani'),
            'count' => $categories->sum('count'), 'categories' => $categories,
            'items' => $items->take(50)->map(fn (object $row) => $this->payload($request, $row))->values(),
            'next_page' => $items->count() > 50 ? $page + 1 : null,
            'payers' => $this->payers($family)->orderBy('u.name')->orderBy('m.id')->get(['m.id', 'u.name'])]);
    }

    public function show(Request $request, string $family, string $expense): JsonResponse
    {
        $row = $this->rows($this->query($request, $family)->where('e.id', $expense))->firstOrFail();

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

        return ['amount_bani' => $amount, 'category' => $data['category'], 'spent_on' => $data['spent_on'],
            'payer_membership_id' => $data['payer_membership_id'], 'note' => $data['note'] ?? null,
            'visibility' => $data['visibility'] ?? 'family'];
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->writable($request);
        $data = $this->content($request, $family);
        $id = (string) Str::uuid();
        DB::table('expenses')->insert($data + ['id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'expense.created', $id);

        return $this->show($request, $family, $id)->setStatusCode(201);
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

        return $this->show($request, $family, $expense);
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
