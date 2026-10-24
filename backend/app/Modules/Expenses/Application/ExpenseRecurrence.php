<?php

namespace App\Modules\Expenses\Application;

use App\Platform\Audit\Audit;
use App\Platform\RecurrenceDates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ExpenseRecurrence
{
    /** @return array{recurrence_notice: string, next_due_on: ?string} */
    public function createNext(string $family, object $expense, int $actor): array
    {
        if (DB::table('expenses')->where('family_id', $family)->where('recurrence_parent_id', $expense->id)->exists()) {
            return ['recurrence_notice' => 'Următoarea plată a fost creată deja.', 'next_due_on' => null];
        }
        if (! DB::table('family_memberships')->where('family_id', $family)->where('user_id', $expense->owner_id)
            ->whereNull('revoked_at')->where('role', '!=', 'limited')->exists()) {
            return ['recurrence_notice' => 'Repetarea s-a oprit: autorul nu mai poate adăuga cheltuieli în familie.', 'next_due_on' => null];
        }
        if (! DB::table('family_memberships')->where('family_id', $family)->where('id', $expense->payer_membership_id)
            ->whereNull('revoked_at')->whereNotNull('user_id')->exists()) {
            return ['recurrence_notice' => 'Repetarea s-a oprit: plătitorul nu mai este membru activ.', 'next_due_on' => null];
        }
        $nextOn = RecurrenceDates::nextDate($expense->recurrence, $expense->recurrence_anchor_on, max($expense->spent_on, now('Europe/Bucharest')->toDateString()));
        if ($nextOn === null) {
            return ['recurrence_notice' => 'Nu mai există o dată disponibilă până la 31 decembrie 2099.', 'next_due_on' => null];
        }
        $id = (string) Str::uuid();
        DB::table('expenses')->insert(['id' => $id, 'family_id' => $family, 'owner_id' => $expense->owner_id,
            'payer_membership_id' => $expense->payer_membership_id, 'amount_bani' => $expense->amount_bani,
            'category' => $expense->category, 'spent_on' => $nextOn, 'note' => $expense->note, 'visibility' => $expense->visibility,
            'payment_status' => 'planned', 'recurrence' => $expense->recurrence, 'recurrence_anchor_on' => $expense->recurrence_anchor_on,
            'recurrence_parent_id' => $expense->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $actor, 'expense.recurrence_created', $id);

        return ['recurrence_notice' => 'Următoarea plată a fost creată.', 'next_due_on' => $nextOn];
    }
}
