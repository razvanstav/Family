<?php

namespace App\Platform\Administration;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class EmailSettingsController
{
    public function show(EmailSettings $settings): JsonResponse
    {
        return response()->json($settings->publicSettings());
    }

    public function update(Request $request, EmailSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'host' => ['sometimes', 'in:'.EmailSettings::HOST],
            'port' => ['sometimes', 'integer', 'in:'.EmailSettings::PORT],
            'url' => ['prohibited'],
            'username' => ['present', 'nullable', 'string', 'max:254', 'regex:/^[^\r\n]*$/'],
            'password' => ['sometimes', 'nullable', 'string', 'max:512', 'regex:/^[^\r\n]*$/'],
            'from_address' => ['present', 'nullable', 'email:rfc', 'max:254'],
            'from_name' => ['present', 'nullable', 'string', 'max:120', 'regex:/^[^\r\n]*$/'],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($settings->update($data, $request->user()->id));
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate(['recipient' => ['required', 'email:rfc', 'max:254']]);
        try {
            Mail::mailer('amora')->raw('Acesta este un email de test trimis din Amora.', function (Message $message) use ($data): void {
                $message->to($data['recipient'])->subject('Amora · Test SMTP');
            });
        } catch (TransportExceptionInterface) {
            return response()->json(['message' => 'Emailul nu a putut fi trimis. Verifică setările salvate, cheia SMTP și expeditorul validat în Brevo.'], 422);
        }

        return response()->json(['message' => 'Emailul de test a fost trimis.']);
    }
}
