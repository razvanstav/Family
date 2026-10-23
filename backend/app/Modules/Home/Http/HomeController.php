<?php

namespace App\Modules\Home\Http;

use App\Modules\Calendar\Contracts\Overview;
use App\Modules\Documents\Contracts\DocumentSummaries;
use App\Modules\Memory\Contracts\MomentSummaries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HomeController
{
    public function __invoke(Request $request, string $family, DocumentSummaries $documents, Overview $calendar, MomentSummaries $moments): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'regex:/^(19\d{2}|20\d{2}|2100)-(0[1-9]|1[0-2])$/']]);
        $actor = $request->user()->id;
        $membership = $request->attributes->get('membership')->id;

        return response()->json(['documents' => $documents->recent($family, $actor, $membership), 'memories' => $moments->forHome($family, $actor)]
            + $calendar->home($family, $actor, $data['month']));
    }
}
