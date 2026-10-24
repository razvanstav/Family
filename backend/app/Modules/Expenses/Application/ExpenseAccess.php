<?php

namespace App\Modules\Expenses\Application;

use App\Modules\Expenses\Contracts\UpcomingPayments;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ExpenseAccess implements UpcomingPayments
{
    public function query(string $family, int $actor): Builder
    {
        return DB::table('expenses as e')->where('e.family_id', $family)->whereNull('e.deleted_at')
            ->where(fn (Builder $access) => $access->where('e.visibility', 'family')->orWhere('e.owner_id', $actor));
    }

    public function rows(Builder $query): Builder
    {
        return $query->join('users as owner', 'owner.id', '=', 'e.owner_id')
            ->join('family_memberships as payer', function ($join) {
                $join->on('payer.family_id', '=', 'e.family_id')->on('payer.id', '=', 'e.payer_membership_id');
            })->join('users as paid_by', 'paid_by.id', '=', 'payer.user_id')
            ->select('e.*', 'owner.name as owner_name', 'paid_by.name as payer_name')
            ->selectRaw('EXISTS (SELECT 1 FROM expenses AS successor WHERE successor.family_id = e.family_id AND successor.recurrence_parent_id = e.id) AS has_next_payment');
    }

    public function payload(object $row, int $actor, string $role): array
    {
        return ['id' => $row->id, 'amount_bani' => (int) $row->amount_bani, 'category' => $row->category,
            'spent_on' => $row->spent_on, 'note' => $row->note, 'visibility' => $row->visibility,
            'owner_name' => $row->owner_name, 'payer_membership_id' => $row->payer_membership_id,
            'payer_name' => $row->payer_name, 'version' => (int) $row->version,
            'payment_status' => $row->payment_status, 'recurrence' => $row->recurrence, 'has_next_payment' => (bool) $row->has_next_payment,
            'can_edit' => $row->owner_id === $actor && $role !== 'limited'];
    }

    public function forHome(string $family, int $actor, string $role): Collection
    {
        $today = now('Europe/Bucharest')->startOfDay();

        return $this->rows($this->query($family, $actor))->where('e.payment_status', 'planned')
            ->whereBetween('e.spent_on', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->orderBy('e.spent_on')->orderBy('e.id')->limit(5)->get()
            ->map(fn (object $row) => $this->payload($row, $actor, $role));
    }
}
