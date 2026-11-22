<?php

namespace Tests\Feature\Api;

use App\Models\ApiEndpoint;
use App\Models\Distribution;
use App\Models\Permission;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEndpointOverride;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProviderLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/v1/provider-ledgers';

    public function test_migration_copies_grants_for_each_action_without_copying_overrides(): void
    {
        $migration = require database_path('migrations/2026_11_22_000001_register_provider_ledger_api_permissions.php');
        $migration->down();

        $reader = $this->roleWith('debt_ledgers.view', 'debt_ledgers.update');
        $writer = $this->roleWith('debt_ledgers.create', 'debt_ledgers.delete');
        $unrelated = $this->roleWith('providers.view');
        $user = $this->authenticate($reader);
        $this->override($user, 'debt_ledgers.view', 'deny');
        $this->getJson(self::ROUTE)->assertForbidden();

        $migration->up();

        $this->assertEqualsCanonicalizing(['provider_ledgers.view', 'provider_ledgers.update'], $reader->permissions()->where('code', 'like', 'provider_ledgers.%')->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['provider_ledgers.create', 'provider_ledgers.delete'], $writer->permissions()->where('code', 'like', 'provider_ledgers.%')->pluck('code')->all());
        $this->assertFalse($unrelated->permissions()->where('code', 'like', 'provider_ledgers.%')->exists());
        $this->getJson(self::ROUTE)->assertOk();
        $this->getJson('/api/v1/debt-ledgers')->assertForbidden();
        $this->postJson(self::ROUTE, [])->assertForbidden();
        $this->deleteJson(self::ROUTE.'/1')->assertForbidden();
    }

    public function test_provider_ledger_overrides_control_only_their_endpoint(): void
    {
        $this->getJson(self::ROUTE)->assertUnauthorized();
        $user = $this->authenticate($this->roleWith('provider_ledgers.view', 'debt_ledgers.view'));
        $entry = ProviderLedger::factory()->manualCharge()->create();
        $this->override($user, 'provider_ledgers.view', 'deny');
        $this->override($user, 'provider_ledgers.create', 'allow');

        $this->getJson(self::ROUTE)->assertForbidden();
        $this->getJson(self::ROUTE.'/'.$entry->id)->assertForbidden();
        $this->getJson('/api/v1/debt-ledgers')->assertOk();
        $this->postJson(self::ROUTE, $this->charge($entry->provider), ['Idempotency-Key' => 'allowed'])->assertCreated();
        $this->postJson('/api/v1/debt-ledgers', [])->assertForbidden();
    }

    public function test_filters_and_pagination_keep_the_admin_order_and_full_running_balance(): void
    {
        $this->authenticate($this->roleWith('provider_ledgers.view'));
        $provider = Provider::factory()->create();
        $older = [
            'provider_id' => $provider->id,
            'transaction_date' => '2026-06-01',
            'provider_received_at' => null,
            'car_number' => null,
        ];
        ProviderLedger::factory()->manualCharge()->create([...$older, 'amount' => '100.0000']);
        ProviderLedger::factory()->manualCharge()->create([...$older, 'amount' => '500.0000'])->delete();
        $sameTime = [
            'provider_id' => $provider->id,
            'transaction_date' => '2026-06-02',
            'provider_received_at' => '2026-06-02 09:00:00',
        ];
        $payment = ProviderLedger::factory()->payment()->create([
            ...$sameTime,
            'notes' => 'Second trip',
            'amount' => '30.00',
            'sort_order' => 2,
        ]);
        $charge = ProviderLedger::factory()->create([
            ...$sameTime,
            'notes' => 'First trip',
            'car_number' => 'TJ-1234',
            'quantity' => '3.500',
            'buy_price' => '11.4286',
            'amount' => '40.00',
            'sort_order' => 1,
            'origin' => 'distribution',
            'distribution_id' => Distribution::factory()->create(['id' => 765432])->id,
        ]);
        $otherProvider = ProviderLedger::factory()->manualCharge()->create([
            ...$sameTime,
            'provider_id' => Provider::factory()->create()->id,
            'car_number' => null,
            'notes' => 'Other provider trip',
            'amount' => '900',
        ]);
        $this->getJson(self::ROUTE.'/'.$otherProvider->id)->assertJsonPath('data.attributes.running_balance', '900.0000');

        $query = self::ROUTE.'?provider_id='.$provider->id.'&search=trip&provider_date_from=2026-06-02&provider_date_to=2026-06-02&per_page=1';
        $this->getJson($query)->assertOk()
            ->assertJsonPath('meta.pagination', ['page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2])
            ->assertJsonPath('data.0.id', (string) $charge->id)
            ->assertJsonPath('data.0.attributes.running_balance', '140.0000');
        $this->getJson($query.'&page=2')->assertOk()
            ->assertJsonPath('data.0.id', (string) $payment->id)
            ->assertJsonPath('data.0.attributes.running_balance', '110.0000');

        $this->getJson(self::ROUTE.'?type=charge&tonnage=3.500&vehicle_number=123&search=TJ-1234')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $charge->id);
        $this->getJson(self::ROUTE.'?search='.$charge->distribution_id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $charge->id);
        $this->getJson(self::ROUTE.'?paid_amount=30.00')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $payment->id);
        $this->getJson(self::ROUTE.'?paid_amount=40.00')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::ROUTE)->assertJsonPath('meta.pagination.per_page', 20);
        $this->getJson(self::ROUTE.'?per_page=101')->assertJsonPath('meta.pagination.per_page', 100);

        $this->getJson(self::ROUTE.'/'.$charge->id)->assertOk()->assertHeader('ETag')
            ->assertJsonPath('data.type', 'provider-ledger')
            ->assertJsonPath('data.attributes.provider.id', (string) $provider->id)
            ->assertJsonPath('data.attributes.product.id', (string) $charge->product_id)
            ->assertJsonPath('data.attributes.distribution.id', (string) $charge->distribution_id)
            ->assertJsonPath('data.attributes.quantity', '3.500')
            ->assertJsonPath('data.attributes.buy_price', '11.4286')
            ->assertJsonPath('data.attributes.running_balance', '140.0000');
    }

    public function test_payment_create_converts_currency_and_replays_original_response_after_an_edit(): void
    {
        config(['app.timezone' => 'Asia/Dushanbe']);
        $this->authenticate($this->roleWith('provider_ledgers.create', 'provider_ledgers.update'));
        $provider = Provider::factory()->create();
        $payment = [
            'provider_id' => (string) $provider->id,
            'type' => 'payment',
            'amount' => '100',
            'currency' => 'USD',
            'exchange_rate' => '9.50',
            'payment_method' => 'cash',
            'payment_purpose' => 'on_behalf_of',
            'payer_name' => 'Farid',
            'provider_received_at' => '2026-06-03T22:30:00Z',
            'notes' => 'Payment note',
            'car_number' => 'TJ-1234',
        ];
        $headers = ['Idempotency-Key' => 'payment-1'];
        $created = $this->postJson(self::ROUTE, $payment, $headers)->assertCreated()->assertHeader('ETag')
            ->assertJsonPath('data.attributes.origin', 'manual')
            ->assertJsonPath('data.attributes.amount', '950.0000')
            ->assertJsonPath('data.attributes.running_balance', '-950.0000')
            ->assertJsonPath('data.attributes.transaction_date', '2026-06-04')
            ->assertJsonPath('data.attributes.provider_received_at', '2026-06-04T03:30:00+05:00')
            ->assertJsonPath('data.attributes.notes', 'Payment note | 100 USD × 9.50 = 950 TJS')
            ->assertJsonPath('data.attributes.car_number', null)
            ->assertJsonPath('data.attributes.payment_method', 'cash')
            ->assertJsonPath('data.attributes.payment_purpose', 'on_behalf_of')
            ->assertJsonPath('data.attributes.payer_name', 'Farid');

        $etag = $created->headers->get('ETag');
        $this->patchJson(self::ROUTE.'/'.$created->json('data.id'), [
            ...$payment,
            'amount' => '930.9999',
            'payment_purpose' => 'labor',
        ], ['If-Match' => $etag])->assertOk()
            ->assertJsonPath('data.attributes.amount', '930.9999')
            ->assertJsonPath('data.attributes.payer_name', null)
            ->assertJsonPath('data.attributes.notes', 'Payment note');

        $replay = $this->postJson(self::ROUTE, $payment, $headers)->assertCreated()->assertHeader('ETag', $etag);
        $this->assertSame($created->getContent(), $replay->getContent());
        $this->postJson(self::ROUTE, [...$payment, 'amount' => '200'], $headers)->assertConflict();
        $this->assertDatabaseCount('provider_ledgers', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);

        $this->travel(24)->hours();
        $this->postJson(self::ROUTE, [...$payment, 'amount' => '200'], $headers)->assertCreated()
            ->assertJsonPath('data.attributes.amount', '1900.0000');
        $this->assertDatabaseCount('provider_ledgers', 2);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_validation_requires_an_active_provider_timestamp_offset_and_payment_create_fields(): void
    {
        $this->authenticate($this->roleWith('provider_ledgers.create'));
        $provider = Provider::factory()->create();
        $charge = $this->charge($provider);
        $headers = ['Idempotency-Key' => 'validation-1'];
        $this->postJson(self::ROUTE, $charge)->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key');
        $this->postJson(self::ROUTE, [...$charge, 'provider_received_at' => '2026-06-03T22:30:00'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('provider_received_at');
        $this->postJson(self::ROUTE, [...$charge, 'provider_received_at' => '2026-02-30T07:00:00+05:00'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('provider_received_at');
        $this->postJson(self::ROUTE, [...$charge, 'provider_id' => Provider::factory()->trashed()->create()->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('provider_id');
        $this->postJson(self::ROUTE, [...$charge, 'type' => 'payment', 'payment_purpose' => 'on_behalf_of'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['currency', 'exchange_rate', 'payment_method', 'payer_name']);
        $this->postJson(self::ROUTE, [
            ...$charge,
            'type' => 'payment',
            'currency' => 'TJS',
            'exchange_rate' => '1',
            'payment_method' => 'cash',
            'payment_purpose' => 'other',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('payment_purpose');
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->assertDatabaseCount('provider_ledgers', 0);
        $this->postJson(self::ROUTE, $charge, $headers)->assertCreated()
            ->assertJsonPath('data.attributes.car_number', 'TJ-1234');
    }

    public function test_a_failed_idempotency_write_rolls_back_the_created_entry(): void
    {
        $this->authenticate($this->roleWith('provider_ledgers.create'));
        $charge = $this->charge(Provider::factory()->create());
        $headers = ['Idempotency-Key' => 'failed-write'];
        $this->withoutExceptionHandling();
        DB::unprepared(<<<'SQL'
            CREATE TEMP TRIGGER reject_idempotency_response
            BEFORE UPDATE OF response ON idempotency_keys
            BEGIN
                SELECT RAISE(ABORT, 'Simulated idempotency write failure');
            END
            SQL);

        try {
            $this->postJson(self::ROUTE, $charge, $headers);
            $this->fail('Expected the idempotency response write to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated idempotency write failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER reject_idempotency_response');
        }

        $this->assertDatabaseCount('provider_ledgers', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->postJson(self::ROUTE, $charge, $headers)->assertCreated();
        $this->assertDatabaseCount('provider_ledgers', 1);
    }

    public function test_manual_updates_and_deletes_require_the_current_etag(): void
    {
        $this->freezeSecond();
        $this->authenticate($this->roleWith('provider_ledgers.view', 'provider_ledgers.update', 'provider_ledgers.delete'));
        $entry = ProviderLedger::factory()->manualCharge()->create();
        $route = self::ROUTE.'/'.$entry->id;
        $charge = $this->charge($entry->provider);
        $originalTag = $this->getJson($route)->assertOk()->headers->get('ETag');

        $this->patchJson($route, $charge)->assertStatus(412);
        $this->deleteJson($route)->assertStatus(412);
        $this->patchJson($route, ['amount' => 120], ['If-Match' => $originalTag])->assertUnprocessable()
            ->assertJsonValidationErrors(['provider_id', 'type', 'provider_received_at']);

        $updated = $this->patchJson($route, $charge, ['If-Match' => $originalTag])->assertOk()
            ->assertJsonPath('data.attributes.amount', '120.0000');
        $updatedTag = $updated->headers->get('ETag');
        $this->assertNotSame($originalTag, $updatedTag);
        $this->getJson($route)->assertHeader('ETag', $updatedTag);
        $this->patchJson($route, $charge, ['If-Match' => $originalTag])->assertStatus(412);

        $noOpTag = $this->patchJson($route, $charge, ['If-Match' => $updatedTag])->assertOk()->headers->get('ETag');
        $this->assertNotSame($updatedTag, $noOpTag);
        $this->deleteJson($route, [], ['If-Match' => $updatedTag])->assertStatus(412);
        $this->deleteJson($route, [], ['If-Match' => $noOpTag])->assertNoContent();
        $this->assertSoftDeleted($entry);
        $this->getJson($route)->assertNotFound();
        $this->patchJson($route, $charge, ['If-Match' => $noOpTag])->assertNotFound();
        $this->deleteJson($route, [], ['If-Match' => $noOpTag])->assertNotFound();
        $this->getJson(self::ROUTE.'/99999')->assertNotFound();
        $this->patchJson(self::ROUTE.'/99999', $charge, ['If-Match' => $noOpTag])->assertNotFound();
        $this->deleteJson(self::ROUTE.'/99999', [], ['If-Match' => $noOpTag])->assertNotFound();
    }

    public function test_all_distribution_derived_entries_remain_readable_and_reject_direct_writes(): void
    {
        $this->authenticate($this->roleWith('provider_ledgers.view', 'provider_ledgers.update', 'provider_ledgers.delete'));
        $entries = [
            ProviderLedger::factory()->create(['origin' => 'manual']),
            ProviderLedger::factory()->create(['origin' => 'distribution']),
            ProviderLedger::factory()->manualCharge()->create(['origin' => 'distribution']),
        ];

        foreach ($entries as $entry) {
            $route = self::ROUTE.'/'.$entry->id;
            $etag = $this->getJson($route)->assertOk()->headers->get('ETag');
            $this->patchJson($route, $this->charge($entry->provider), ['If-Match' => $etag])->assertConflict()
                ->assertJsonPath('message', 'Distribution-derived entries must be changed through /api/v1/distributions/{id}.');
            $this->deleteJson($route, [], ['If-Match' => $etag])->assertConflict();
            $this->getJson($route)->assertOk()->assertHeader('ETag', $etag);
        }
    }

    private function roleWith(string ...$codes): Role
    {
        $role = Role::create(['code' => fake()->uuid(), 'name' => 'Test role']);
        $role->permissions()->attach(Permission::whereIn('code', $codes)->pluck('id'));

        return $role;
    }

    private function authenticate(Role $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $token = fake()->uuid();
        DB::table('personal_access_tokens')->insert(['user_id' => $user->id, 'name' => 'Test', 'token' => hash('sha256', $token)]);
        $this->withToken($token);

        return $user;
    }

    private function override(User $user, string $endpoint, string $effect): void
    {
        UserEndpointOverride::create([
            'user_id' => $user->id,
            'api_endpoint_id' => ApiEndpoint::where('endpoint_key', $endpoint)->firstOrFail()->id,
            'effect' => $effect,
            'reason' => 'Test override',
        ]);
    }

    private function charge(Provider $provider): array
    {
        return [
            'provider_id' => (string) $provider->id,
            'type' => 'charge',
            'amount' => '120.00',
            'provider_received_at' => '2026-06-03T07:00:00+05:00',
            'car_number' => 'TJ-1234',
        ];
    }
}
