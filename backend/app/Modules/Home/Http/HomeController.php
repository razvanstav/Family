<?php

namespace App\Modules\Home\Http;

use App\Modules\Calendar\Contracts\Overview;
use App\Modules\Documents\Contracts\DocumentSummaries;
use App\Modules\Expenses\Contracts\UpcomingPayments;
use App\Modules\Memory\Contracts\MomentSummaries;
use App\Modules\Tasks\Contracts\TaskSummaries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HomeController
{
    public function __invoke(Request $request, string $family, DocumentSummaries $documents, Overview $calendar, MomentSummaries $moments, TaskSummaries $tasks, UpcomingPayments $payments): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'regex:/^(19\d{2}|20\d{2}|2100)-(0[1-9]|1[0-2])$/']]);
        $actor = $request->user()->id;
        $membership = $request->attributes->get('membership');

        return response()->json(['documents' => $documents->recent($family, $actor, $membership->id), 'memories' => $moments->forHome($family, $actor),
            'tasks' => $tasks->forHome($family, $actor, $membership->id, $membership->role),
            'upcoming_payments' => $payments->forHome($family, $actor, $membership->role)]
            + $calendar->home($family, $actor, $data['month']));
    }
}
