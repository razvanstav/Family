<?php

namespace App\Platform\Administration;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SupportRequestController
{
    private const TYPES = ['support', 'privacy', 'withdrawal', 'illegal-content'];

    private const STATUSES = ['received', 'in_progress', 'closed'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:8000'],
            'file' => ['prohibited'], 'attachments' => ['prohibited'],
            'id' => ['prohibited'], 'reference' => ['prohibited'],
            'status' => ['prohibited'], 'version' => ['prohibited'],
        ]);
        $reference = 'AMR-'.Str::upper(Str::random(12));
        $createdAt = now()->startOfSecond();
        DB::transaction(function () use ($data, $reference, $createdAt): void {
            DB::select("SELECT set_config('app.support_requests_access', 'submission', true)");
            DB::table('support_requests')->insert([
                'id' => (string) Str::uuid(), 'reference' => $reference,
                'purpose' => $data['type'], 'status' => 'received', 'version' => 1,
                'payload_ciphertext' => Crypt::encryptString(json_encode([
                    'name' => $data['name'], 'email' => Str::lower($data['email']),
                    'subject' => $data['subject'], 'message' => $data['message'],
                ], JSON_THROW_ON_ERROR)),
                'created_at' => $createdAt, 'updated_at' => $createdAt,
            ]);
        });

        return response()->json([
            'reference' => $reference, 'status' => 'received',
            'created_at' => CarbonImmutable::instance($createdAt)->toISOString(),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);
        $page = (int) ($data['page'] ?? 1);
        $rows = DB::table('support_requests')
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->orderByDesc('created_at')->orderByDesc('id')->offset(($page - 1) * 25)->limit(26)->get();

        return response()->json([
            'items' => $rows->take(25)->map(fn (object $row): array => $this->payload($row))->values(),
            'page' => $page, 'has_more' => $rows->count() > 25,
        ]);
    }

    public function update(Request $request, string $supportRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        $row = DB::table('support_requests')->where('id', $supportRequest)->lockForUpdate()->firstOrFail();
        abort_unless((int) $row->version === (int) $data['version'], 412, 'Solicitarea s-a schimbat. Reîncarcă lista înainte de a salva.');
        if ($row->status !== $data['status']) {
            $row->status = $data['status'];
            $row->version++;
            $row->updated_at = now();
            DB::table('support_requests')->where('id', $supportRequest)->update([
                'status' => $row->status, 'version' => $row->version, 'updated_at' => $row->updated_at,
            ]);
        }

        return response()->json($this->payload($row));
    }

    /** @return array<string, mixed> */
    private function payload(object $row): array
    {
        $content = json_decode(Crypt::decryptString($row->payload_ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return [
            'id' => $row->id, 'reference' => $row->reference, 'type' => $row->purpose,
            'status' => $row->status, 'version' => (int) $row->version,
            'name' => $content['name'], 'email' => $content['email'],
            'subject' => $content['subject'], 'message' => $content['message'],
            'created_at' => CarbonImmutable::parse($row->created_at)->toISOString(),
            'updated_at' => CarbonImmutable::parse($row->updated_at)->toISOString(),
        ];
    }
}
