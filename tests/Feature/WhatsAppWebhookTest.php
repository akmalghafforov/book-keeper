<?php

namespace Tests\Feature;

use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_the_whatsapp_webhook_with_meta_query_parameters(): void
    {
        config()->set('services.whatsapp.webhook_verify_token', 'verify-token');

        $response = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=challenge-value');

        $response
            ->assertOk()
            ->assertContent('challenge-value');
    }

    public function test_it_rejects_webhook_verification_with_an_invalid_token(): void
    {
        config()->set('services.whatsapp.webhook_verify_token', 'verify-token');

        $response = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong-token&hub.challenge=challenge-value');

        $response->assertForbidden();
    }

    public function test_it_persists_a_valid_signed_whatsapp_webhook_payload(): void
    {
        config()->set('services.whatsapp.app_secret', 'app-secret');

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15551234567',
                                    'phone_number_id' => '987654321',
                                ],
                                'messages' => [
                                    [
                                        'from' => '15550001111',
                                        'id' => 'wamid.HBgN',
                                        'timestamp' => '1713279600',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Hello',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $jsonPayload, 'app-secret');

        $response = $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $jsonPayload,
        );

        $response
            ->assertOk()
            ->assertContent('EVENT_RECEIVED');

        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'meta_object' => 'whatsapp_business_account',
            'webhook_field' => 'messages',
        ]);

        $event = WhatsAppWebhookEvent::query()->firstOrFail();

        $this->assertSame($payload, $event->payload);
    }

    public function test_it_rejects_a_whatsapp_webhook_with_an_invalid_signature(): void
    {
        config()->set('services.whatsapp.app_secret', 'app-secret');

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            ],
            $payload,
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('whatsapp_webhook_events', 0);
    }
}
