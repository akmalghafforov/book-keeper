<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('services.whatsapp.webhook_verify_token');

        if ($verifyToken === '') {
            Log::error('WhatsApp webhook verification requested without a configured verify token.');

            return response('Webhook verify token is not configured.', 500);
        }

        $mode = $this->queryValue($request, 'hub_mode', 'hub.mode');
        $token = $this->queryValue($request, 'hub_verify_token', 'hub.verify_token');
        $challenge = $this->queryValue($request, 'hub_challenge', 'hub.challenge');

        if ($mode !== 'subscribe' || ! is_string($token) || ! hash_equals($verifyToken, $token) || ! is_string($challenge)) {
            Log::warning('WhatsApp webhook verification failed.', [
                'mode' => $mode,
                'has_challenge' => is_string($challenge),
            ]);

            abort(403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request): Response
    {
        $appSecret = (string) config('services.whatsapp.app_secret');

        if ($appSecret === '') {
            Log::error('WhatsApp webhook received without a configured app secret.');

            return response('WhatsApp app secret is not configured.', 500);
        }

        if (! $this->hasValidSignature($request, $appSecret)) {
            Log::warning('WhatsApp webhook signature validation failed.');

            abort(403);
        }

        $rawPayload = $request->getContent();

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('WhatsApp webhook contained invalid JSON.');

            return response('Invalid JSON payload.', 400);
        }

        $event = WhatsAppWebhookEvent::query()->create([
            'meta_object' => data_get($payload, 'object'),
            'webhook_field' => data_get($payload, 'entry.0.changes.0.field'),
            'payload' => $payload,
            'received_at' => now(),
        ]);

        Log::channel((string) config('services.whatsapp.log_channel'))->info('WhatsApp webhook received.', [
            'webhook_event_id' => $event->id,
            'meta_object' => $event->meta_object,
            'webhook_field' => $event->webhook_field,
            'entry_count' => count($payload['entry'] ?? []),
        ]);

        return response('EVENT_RECEIVED', 200);
    }

    private function hasValidSignature(Request $request, string $appSecret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    private function queryValue(Request $request, string $normalizedKey, string $dottedKey): mixed
    {
        return $request->query($normalizedKey, $request->query($dottedKey));
    }
}
