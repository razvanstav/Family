<?php

namespace App\Platform\Administration;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AdminOverviewController
{
    public function __invoke(): JsonResponse
    {
        $since = now()->subDays(30);
        $users = DB::table('users')->selectRaw('COUNT(*) AS total, COUNT(*) FILTER (WHERE created_at >= ?) AS last_30_days', [$since])->first();
        $email = DB::table('email_deliveries')->selectRaw("COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 'sent') AS sent, COUNT(*) FILTER (WHERE status = 'failed') AS failed, COUNT(*) FILTER (WHERE created_at >= ?) AS last_30_days", [$since])->first();
        $recent = DB::table('email_deliveries')->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get()
            ->map(fn (object $delivery): array => [
                'id' => (int) $delivery->id, 'recipient' => $delivery->recipient, 'subject' => $delivery->subject,
                'status' => $delivery->status,
                'sent_at' => $delivery->sent_at === null ? null : CarbonImmutable::parse($delivery->sent_at)->toISOString(),
                'created_at' => CarbonImmutable::parse($delivery->created_at)->toISOString(),
            ]);

        return response()->json([
            'users' => ['total' => (int) $users->total, 'last_30_days' => (int) $users->last_30_days],
            'families' => ['total' => DB::table('families')->count()],
            'email' => ['total' => (int) $email->total, 'sent' => (int) $email->sent, 'failed' => (int) $email->failed, 'last_30_days' => (int) $email->last_30_days],
            'recent_emails' => $recent,
        ]);
    }
}
