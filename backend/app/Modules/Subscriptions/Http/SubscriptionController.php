<?php

namespace App\Modules\Subscriptions\Http;

use App\Modules\Subscriptions\Application\SubscriptionAccess;
use App\Platform\Audit\Audit;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class SubscriptionController
{
    public function __construct(private readonly SubscriptionAccess $access) {}

    private function query(Request $request, string $family, bool $pending = true): Builder
    {
        return $this->access->query($family, $request->user()->id, $pending);
    }

    private function payload(Request $request, string $family, object $row): array
    {
        return $this->access->payload($row, $family, $request->user()->id, $request->attributes->get('membership')->role);
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $data = $request->validate(['scope' => ['sometimes', Rule::in(['family', 'shared'])], 'page' => 'sometimes|integer|min:1|max:100000']);
        $query = $this->query($request, $family);
        if (($data['scope'] ?? 'family') === 'shared') {
            $query->where('s.family_id', '<>', $family)->whereIn('invite.status', ['pending', 'accepted']);
        } else {
            $query->where('s.family_id', $family);
        }
        $page = (int) ($data['page'] ?? 1);
        $rows = $this->access->rows($query)->orderBy('s.service_name')->orderBy('s.id')->offset(($page - 1) * 50)->limit(51)->get();

        return response()->json(['items' => $rows->take(50)->map(fn (object $row) => $this->payload($request, $family, $row))->values(),
            'next_page' => $rows->count() > 50 ? $page + 1 : null]);
    }

    public function show(Request $request, string $family, string $subscription): JsonResponse
    {
        $row = $this->access->rows($this->query($request, $family)->where('s.id', $subscription))->firstOrFail();

        return response()->json($this->payload($request, $family, $row));
    }

    private function writable(Request $request): void
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Accesul tău permite numai consultarea abonamentelor.');
    }

    private function version(Request $request, object $row): void
    {
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        abort_unless($request->header('If-Match') === '"'.$row->version.'"', 412, 'Abonamentul s-a schimbat. Reîncarcă înainte de a salva.');
    }

    private function editable(Request $request, string $family, string $subscription): object
    {
        $this->writable($request);
        $row = $this->access->rows($this->query($request, $family)->where('s.id', $subscription))->lock('for update of s')->firstOrFail();
        abort_unless($row->family_id === $family && $row->owner_id === $request->user()->id, 403, 'Numai autorul poate modifica abonamentul.');
        $this->version($request, $row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function content(Request $request, bool $creating): array
    {
        $action = $request->input('secrets_action', 'keep');
        $data = $request->validate([
            'service_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/'],
            'service_name' => 'required|string|max:120', 'website_url' => 'nullable|string|max:2048|url:https',
            'amount' => ['nullable', 'string', 'regex:/^(?:0|[1-9]\d{0,5})(?:[.,]\d{1,2})?$/'],
            'currency' => ['required', Rule::in(['RON', 'EUR', 'USD'])],
            'recurrence' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'next_due_on' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'note' => 'nullable|string|max:1000', 'secrets_action' => ['sometimes', Rule::in(['keep', 'replace', 'remove'])],
            'login' => ['nullable', 'string', 'max:500', Rule::prohibitedIf($action !== 'replace')],
            'password' => ['nullable', 'string', 'max:500', Rule::prohibitedIf($action !== 'replace')],
            'pin' => ['nullable', 'string', 'max:32', Rule::prohibitedIf($action !== 'replace')],
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'amount_minor' => 'prohibited',
            'version' => 'prohibited', 'credentials_ciphertext' => 'prohibited', 'recipient_id' => 'prohibited', 'access' => 'prohibited',
        ]);
        $service = trim($data['service_name']);
        if ($service === '') {
            throw ValidationException::withMessages(['service_name' => 'Introdu numele serviciului.']);
        }
        $url = $data['website_url'] ?? null;
        if ($url !== null && (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
            throw ValidationException::withMessages(['website_url' => 'Linkul serviciului nu trebuie să conțină date de autentificare.']);
        }
        $amount = null;
        if (($data['amount'] ?? null) !== null) {
            [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $data['amount'])), 2, '');
            $amount = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        }
        $values = ['service_key' => $data['service_key'], 'service_name' => $service, 'website_url' => $url,
            'amount_minor' => $amount, 'currency' => $data['currency'], 'recurrence' => $data['recurrence'],
            'next_due_on' => $data['next_due_on'] ?? null, 'note' => $data['note'] ?? null];
        if ($action === 'remove' || ($creating && $action === 'keep')) {
            $values['credentials_ciphertext'] = null;
        } elseif ($action === 'replace') {
            $credentials = ['login' => $data['login'] ?? null, 'password' => $data['password'] ?? null, 'pin' => $data['pin'] ?? null];
            $values['credentials_ciphertext'] = count(array_filter($credentials, fn ($value) => $value !== null && $value !== '')) === 0
                ? null : Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR));
        }

        return $values;
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->writable($request);
        $data = $this->content($request, true);
        $id = (string) Str::uuid();
        DB::table('subscriptions')->insert($data + ['id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'subscription.created', $id);

        return $this->show($request, $family, $id)->setStatusCode(201);
    }

    public function update(Request $request, string $family, string $subscription): JsonResponse
    {
        $row = $this->editable($request, $family, $subscription);
        $data = $this->content($request, false);
        DB::table('subscriptions')->where('id', $subscription)->where('family_id', $family)
            ->update($data + ['version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'subscription.updated', $subscription);

        return $this->show($request, $family, $subscription);
    }

    public function destroy(Request $request, string $family, string $subscription): Response
    {
        $row = $this->editable($request, $family, $subscription);
        DB::table('subscriptions')->where('id', $subscription)->where('family_id', $family)
            ->update(['deleted_at' => now(), 'credentials_ciphertext' => null, 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'subscription.deleted', $subscription);

        return response()->noContent();
    }

    public function credentials(Request $request, string $family, string $subscription): JsonResponse
    {
        $row = $this->query($request, $family, false)->where('s.id', $subscription)->first(['s.credentials_ciphertext']);
        abort_unless($row, 404);
        $credentials = $row->credentials_ciphertext === null ? ['login' => null, 'password' => null, 'pin' => null]
            : json_decode(Crypt::decryptString($row->credentials_ciphertext), true, flags: JSON_THROW_ON_ERROR);
        Audit::record($family, $request->user()->id, 'subscription.credentials_viewed', $subscription);

        return response()->json($credentials);
    }

    private function shareItems(string $subscription): array
    {
        return DB::table('subscription_shares as share')->join('users as recipient', 'recipient.id', '=', 'share.recipient_id')
            ->where('share.subscription_id', $subscription)->whereIn('share.status', ['pending', 'accepted'])->orderBy('recipient.name')->orderBy('share.id')
            ->get(['share.id', 'recipient.email', 'recipient.name', 'share.status', 'share.created_at', 'share.expires_at'])
            ->map(fn (object $row) => ['id' => $row->id, 'email' => $row->email, 'name' => $row->name, 'status' => $row->status,
                'created_at' => (new DateTimeImmutable($row->created_at))->format(DATE_ATOM),
                'expires_at' => $row->expires_at === null ? null : (new DateTimeImmutable($row->expires_at))->format(DATE_ATOM)])->all();
    }

    public function shares(Request $request, string $family, string $subscription): JsonResponse
    {
        $row = $this->access->rows($this->query($request, $family)->where('s.id', $subscription))->firstOrFail();
        abort_unless($row->family_id === $family && $row->owner_id === $request->user()->id, 403);

        return response()->json(['items' => $this->shareItems($subscription)]);
    }

    private function sharingResponse(Request $request, string $family, string $subscription): JsonResponse
    {
        return response()->json(['subscription' => $this->show($request, $family, $subscription)->getData(true), 'items' => $this->shareItems($subscription)]);
    }

    private function bump(object $row): void
    {
        DB::table('subscriptions')->where('id', $row->id)->update(['version' => $row->version + 1, 'updated_at' => now()]);
    }

    public function share(Request $request, string $family, string $subscription): JsonResponse
    {
        $row = $this->editable($request, $family, $subscription);
        $data = $request->validate(['email' => 'required|string|email:rfc|max:254']);
        $email = Str::lower(trim($data['email']));
        $recipient = DB::table('users')->whereRaw('lower(email) = ?', [$email])->first(['id']);
        if (! $recipient) {
            throw ValidationException::withMessages(['email' => 'Nu există un cont cu această adresă de email.']);
        }
        if ($recipient->id === $request->user()->id || DB::table('family_memberships')->where('family_id', $family)->where('user_id', $recipient->id)->whereNull('revoked_at')->exists()) {
            throw ValidationException::withMessages(['email' => 'Această persoană are deja acces prin familie.']);
        }
        $existing = DB::table('subscription_shares')->where('subscription_id', $subscription)->where('recipient_id', $recipient->id)->first();
        $expired = $existing && $existing->status === 'pending' && DB::table('subscription_shares')->where('id', $existing->id)->where('expires_at', '<=', now())->exists();
        if ($existing && in_array($existing->status, ['pending', 'accepted'], true) && ! $expired) {
            throw ValidationException::withMessages(['email' => 'Abonamentul este deja partajat cu această persoană.']);
        }
        $token = bin2hex(random_bytes(32));
        $invitation = ['status' => 'pending', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'updated_at' => now()];
        if ($existing) {
            DB::table('subscription_shares')->where('id', $existing->id)->update($invitation);
        } else {
            DB::table('subscription_shares')->insert($invitation + ['id' => (string) Str::uuid(), 'family_id' => $family, 'subscription_id' => $subscription,
                'recipient_id' => $recipient->id, 'created_at' => now()]);
        }
        $this->bump($row);
        Audit::record($family, $request->user()->id, 'subscription.shared', $subscription);

        $response = $this->sharingResponse($request, $family, $subscription);

        return $response->setData($response->getData(true) + ['invitation_code' => $token]);
    }

    public function revoke(Request $request, string $family, string $subscription, string $share): JsonResponse
    {
        $row = $this->editable($request, $family, $subscription);
        $invitation = DB::table('subscription_shares')->where('subscription_id', $subscription)->where('id', $share)->whereIn('status', ['pending', 'accepted'])->firstOrFail();
        DB::table('subscription_shares')->where('id', $invitation->id)->update(['status' => 'revoked', 'token_hash' => null, 'expires_at' => null, 'updated_at' => now()]);
        $this->bump($row);
        Audit::record($family, $request->user()->id, 'subscription.revoked', $subscription);

        return $this->sharingResponse($request, $family, $subscription);
    }

    private function answer(Request $request, string $family, string $subscription, string $status): ?JsonResponse
    {
        $row = $this->access->rows($this->query($request, $family)->where('s.id', $subscription))->lock('for update of s')->firstOrFail();
        abort_unless($row->family_id !== $family && $row->invite_status === 'pending', 409, 'Invitația nu mai este disponibilă.');
        $this->version($request, $row);
        if ($status === 'accepted') {
            $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
            $invitation = DB::table('subscription_shares')->where('subscription_id', $subscription)->where('recipient_id', $request->user()->id)
                ->where('status', 'pending')->where('expires_at', '>', now())->first(['token_hash']);
            abort_unless($invitation && hash_equals($invitation->token_hash, hash('sha256', $data['token'])), 403, 'Codul invitației este incorect sau a expirat.');
        }
        $this->bump($row);
        DB::table('subscription_shares')->where('subscription_id', $subscription)->where('recipient_id', $request->user()->id)
            ->where('status', 'pending')->update(['status' => $status, 'token_hash' => null, 'expires_at' => null, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'subscription.'.$status, $subscription);

        return $status === 'accepted' ? $this->show($request, $family, $subscription) : null;
    }

    public function accept(Request $request, string $family, string $subscription): JsonResponse
    {
        return $this->answer($request, $family, $subscription, 'accepted');
    }

    public function decline(Request $request, string $family, string $subscription): Response
    {
        $this->answer($request, $family, $subscription, 'declined');

        return response()->noContent();
    }
}
