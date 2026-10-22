<?php

namespace App\Modules\Home\Http;

use App\Modules\Calendar\Application\CalendarOverview;
use App\Modules\Documents\Application\DocumentAccess;
use App\Modules\Memory\Application\RecentMoments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HomeController
{
    public function __invoke(Request $request, string $family, DocumentAccess $documents, CalendarOverview $calendar, RecentMoments $moments): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'regex:/^(19\d{2}|20\d{2}|2100)-(0[1-9]|1[0-2])$/']]);
        $actor = $request->user()->id;
        $membership = $request->attributes->get('membership')->id;

        return response()->json(['documents' => $documents->recent($family, $actor, $membership), 'memories' => $moments->forHome($family, $actor)]
            + $calendar->home($family, $actor, $data['month']));
    }
}
