<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->provider = Provider::factory()->create([
            'name' => 'North Cement',
        ]);
    }

    public function test_index_lists_provider_ledgers(): void
    {
        $chargeLedger = ProviderLedger::factory()->create([
            'provider_id' => $this->provider->id,
            'amount' => '125.7500',
            'car_number' => 'AA-1234',
            'quantity' => '4.000',
        ]);
        ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
            'amount' => '25.0000',
            'notes' => 'Cash payment',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.provider-ledgers.index'));

        $response
            ->assertOk()
            ->assertSee('Provider Ledgers')
            ->assertSeeInOrder([
                __('Provider Date'),
                __('Tonnage'),
                __('Price'),
                __('Total amount'),
                __('Vehicle number'),
                __('Paid amount'),
                __('Balance'),
                __('Provider'),
                __('Product'),
                __('Actions'),
            ])
            ->assertSee('North Cement')
            ->assertSee('AA-1234')
            ->assertSee('4.000')
            ->assertDontSee(route('admin.reports.export-provider-ledger-debt', $chargeLedger), false)
            ->assertSee('25.0000');
    }

    public function test_index_displays_full_history_running_balances_and_split_charge_payment_columns(): void
    {
        ProviderLedger::factory()->create([
            'provider_id' => $this->provider->id,
            'amount' => '100.0000',
            'quantity' => '3.500',
            'buy_price' => '28.5714',
            'car_number' => 'AA-1000',
            'transaction_date' => '2026-06-01',
            'provider_received_at' => '2026-06-01 09:00:00',
        ]);
        ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
            'amount' => '30.0000',
            'transaction_date' => '2026-06-02',
            'provider_received_at' => '2026-06-02 09:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.provider-ledgers.index', [
            'date_from' => '2026-06-02',
            'date_to' => '2026-06-02',
        ]));

        $response
            ->assertOk()
            ->assertSee('30.0000')
            ->assertSee('70.0000')
            ->assertSee('-')
            ->assertDontSee('AA-1000');
    }

    public function test_index_does_not_expose_provider_ledger_reordering(): void
    {
        ProviderLedger::factory()->create([
            'provider_id' => $this->provider->id,
            'car_number' => 'AA-1111',
            'transaction_date' => '2026-06-03',
        ]);
        ProviderLedger::factory()->create([
            'provider_id' => $this->provider->id,
            'car_number' => 'BB-2222',
            'transaction_date' => '2026-06-03',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.provider-ledgers.index'))
            ->assertOk()
            ->assertDontSee('Order')
            ->assertDontSee('Move earlier')
            ->assertDontSee('Move later')
            ->assertDontSee('provider-ledgers.move');
    }

    public function test_index_sorts_manually_created_provider_ledgers_by_transaction_datetime(): void
    {
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'amount' => '100.0000',
            'car_number' => 'AA-1111',
            'transaction_date' => '03/06/2026 09:00',
        ]);
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'amount' => '200.0000',
            'car_number' => 'BB-2222',
            'transaction_date' => '03/06/2026 15:30',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.provider-ledgers.index'))
            ->assertOk()
            ->assertSeeInOrder(['BB-2222', 'AA-1111']);
    }

    public function test_create_prefills_selected_provider(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.provider-ledgers.create', [
            'provider_id' => $this->provider->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Add Provider Ledger Entry')
            ->assertSee('charge')
            ->assertSee('payment')
            ->assertSee('<option value="cash" selected>cash</option>', false)
            ->assertSee('name="currency"', false)
            ->assertSee('<option value="TJS" selected>TJS</option>', false)
            ->assertSee('<option value="USD"', false)
            ->assertSee('<option value="EUR"', false)
            ->assertSee('<option value="UZS"', false)
            ->assertSee('<option value="RUB"', false)
            ->assertSee('value="1"', false)
            ->assertSee(__('Converted amount'), false)
            ->assertSee('North Cement');
    }

    public function test_create_uses_the_application_datetime_as_the_default(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 16, 45, 0, config('app.timezone')));

        try {
            $this->actingAs($this->user)
                ->get(route('admin.provider-ledgers.create'))
                ->assertOk()
                ->assertSee("defaultDate: '09/8/2026 16:45'", false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_store_creates_provider_payment(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'amount' => '25.2500',
            'currency' => 'TJS',
            'exchange_rate' => 1,
            'transaction_date' => '03/06/2026 14:15',
            'notes' => 'Cash payment',
        ]);

        $response->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '25.2500',
            'transaction_date' => '2026-06-03 00:00:00',
            'provider_received_at' => '2026-06-03 14:15:00',
            'notes' => 'Cash payment',
            'payment_method' => 'cash',
        ]);
    }

    public function test_store_creates_provider_debt(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'amount' => '125.7500',
            'car_number' => 'AA-1234',
            'transaction_date' => '03/06/2026 09:45',
            'notes' => 'Manual supplier debt',
        ]);

        $response->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'distribution_id' => null,
            'product_id' => null,
            'car_number' => 'AA-1234',
            'quantity' => null,
            'buy_price' => null,
            'amount' => '125.7500',
            'transaction_date' => '2026-06-03 00:00:00',
            'provider_received_at' => '2026-06-03 09:45:00',
            'notes' => 'Manual supplier debt',
        ]);
    }

    public function test_store_validates_positive_amount(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '0',
            'transaction_date' => '03/06/2026 12:00',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_payment_method_is_required_and_accepts_card_for_provider_payments(): void
    {
        $payload = [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '25.0000',
            'currency' => 'TJS',
            'exchange_rate' => 1,
            'transaction_date' => '03/06/2026 12:00',
        ];

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.store'), $payload)
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.store'), [...$payload, 'payment_method' => 'invalid'])
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.store'), [...$payload, 'payment_method' => 'card'])
            ->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', ['payment_method' => 'card']);
    }

    public function test_provider_charge_clears_a_submitted_payment_method(): void
    {
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'payment_method' => 'alif',
            'amount' => '25.0000',
            'transaction_date' => '03/06/2026 12:00',
        ])->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', ['type' => 'charge', 'payment_method' => null]);
    }

    public function test_show_displays_provider_ledger_details(): void
    {
        $ledger = ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
            'amount' => '25.0000',
            'notes' => 'Bank transfer',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.provider-ledgers.show', $ledger));

        $response
            ->assertOk()
            ->assertSee('Provider Ledger Entry')
            ->assertSee('North Cement')
            ->assertSee('Bank transfer');
    }

    public function test_payment_details_show_the_saved_method_and_legacy_entries_show_na(): void
    {
        $ledger = ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
            'payment_method' => 'ds',
        ]);
        $legacyLedger = ProviderLedger::factory()->payment()->create(['provider_id' => $this->provider->id]);

        $this->actingAs($this->user)->get(route('admin.provider-ledgers.show', $ledger))
            ->assertOk()
            ->assertSee('Payment Method')
            ->assertSee('ДС');
        $this->actingAs($this->user)->get(route('admin.provider-ledgers.show', $legacyLedger))
            ->assertOk()
            ->assertSee(__('N/A'));
    }

    public function test_update_modifies_provider_payment(): void
    {
        $ledger = ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
            'amount' => '25.0000',
        ]);

        $response = $this->actingAs($this->user)->put(route('admin.provider-ledgers.update', $ledger), [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'payment_method' => 'eo',
            'amount' => '30.5000',
            'transaction_date' => '04/06/2026 16:20',
            'notes' => 'Updated payment',
        ]);

        $response->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', [
            'id' => $ledger->id,
            'type' => 'payment',
            'amount' => '30.5000',
            'transaction_date' => '2026-06-04 00:00:00',
            'provider_received_at' => '2026-06-04 16:20:00',
            'notes' => 'Updated payment',
            'payment_method' => 'eo',
        ]);
    }

    public function test_update_modifies_manual_provider_debt(): void
    {
        $ledger = ProviderLedger::factory()->manualCharge()->create([
            'provider_id' => $this->provider->id,
            'amount' => '125.0000',
            'car_number' => 'AA-1234',
        ]);

        $response = $this->actingAs($this->user)->put(route('admin.provider-ledgers.update', $ledger), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'payment_method' => 'alif',
            'amount' => '130.7500',
            'car_number' => 'BB-5678',
            'transaction_date' => '04/06/2026 08:05',
            'notes' => 'Updated manual debt',
        ]);

        $response->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', [
            'id' => $ledger->id,
            'type' => 'charge',
            'distribution_id' => null,
            'product_id' => null,
            'car_number' => 'BB-5678',
            'quantity' => null,
            'buy_price' => null,
            'amount' => '130.7500',
            'transaction_date' => '2026-06-04 00:00:00',
            'provider_received_at' => '2026-06-04 08:05:00',
            'notes' => 'Updated manual debt',
            'payment_method' => null,
        ]);
    }

    public function test_destroy_soft_deletes_provider_payment(): void
    {
        $ledger = ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('admin.provider-ledgers.destroy', $ledger));

        $response->assertRedirect(route('admin.provider-ledgers.index'));
        $this->assertSoftDeleted('provider_ledgers', ['id' => $ledger->id]);
    }

    public function test_destroy_soft_deletes_manual_provider_debt(): void
    {
        $ledger = ProviderLedger::factory()->manualCharge()->create([
            'provider_id' => $this->provider->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('admin.provider-ledgers.destroy', $ledger));

        $response->assertRedirect(route('admin.provider-ledgers.index'));
        $this->assertSoftDeleted('provider_ledgers', ['id' => $ledger->id]);
    }

    public function test_generated_charge_cannot_be_manually_changed(): void
    {
        $ledger = ProviderLedger::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.provider-ledgers.edit', $ledger))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->put(route('admin.provider-ledgers.update', $ledger), [
                'provider_id' => $this->provider->id,
                'type' => 'charge',
                'amount' => '30.5000',
                'transaction_date' => '04/06/2026 10:00',
            ])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete(route('admin.provider-ledgers.destroy', $ledger))
            ->assertForbidden();
    }

    public function test_payment_purpose_is_persisted_displayed_and_cleared_when_changing_to_charge(): void
    {
        $payload = [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'amount' => '25.0000',
            'currency' => 'TJS',
            'exchange_rate' => 1,
            'transaction_date' => '03/06/2026 12:00',
        ];

        foreach (['vehicle_and_labor', 'vehicle', 'labor'] as $purpose) {
            $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
                ...$payload,
                'payment_purpose' => $purpose,
            ])->assertRedirect(route('admin.provider-ledgers.index'));

            $this->assertDatabaseHas('provider_ledgers', ['payment_purpose' => $purpose, 'payer_name' => null]);
        }

        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            ...$payload,
            'payment_purpose' => 'on_behalf_of',
        ])->assertSessionHasErrors('payer_name');

        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            ...$payload,
            'payment_purpose' => 'on_behalf_of',
            'payer_name' => 'Farid',
        ])->assertRedirect(route('admin.provider-ledgers.index'));

        $ledger = ProviderLedger::latest('id')->firstOrFail();
        $this->assertDatabaseHas('provider_ledgers', [
            'id' => $ledger->id,
            'payment_purpose' => 'on_behalf_of',
            'payer_name' => 'Farid',
        ]);

        $this->actingAs($this->user)->get(route('admin.provider-ledgers.show', $ledger))
            ->assertOk()
            ->assertSee('Аз номи Farid');
        $this->actingAs($this->user)->get(route('admin.provider-ledgers.index'))
            ->assertOk()
            ->assertDontSee('Аз номи Farid');

        $this->actingAs($this->user)->put(route('admin.provider-ledgers.update', $ledger), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'payment_purpose' => 'on_behalf_of',
            'payer_name' => 'Farid',
            'amount' => '25.0000',
            'transaction_date' => '03/06/2026 12:00',
        ])->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', ['id' => $ledger->id, 'payment_purpose' => null, 'payer_name' => null]);
    }

    public function test_foreign_currency_provider_payments_are_converted_and_audited(): void
    {
        $payment = [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'amount' => '100',
            'currency' => 'USD',
            'exchange_rate' => '9.50',
            'transaction_date' => '03/06/2026 12:00',
        ];

        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [...$payment, 'notes' => 'Existing note'])
            ->assertRedirect(route('admin.provider-ledgers.index'));
        $this->assertDatabaseHas('provider_ledgers', ['amount' => 950, 'notes' => 'Existing note | 100 USD × 9.50 = 950 TJS']);

        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), $payment)
            ->assertRedirect(route('admin.provider-ledgers.index'));
        $this->assertDatabaseHas('provider_ledgers', ['notes' => '100 USD × 9.50 = 950 TJS']);
    }

    public function test_provider_payment_currency_fields_are_validated_and_tjs_rate_is_forced(): void
    {
        $payment = ['provider_id' => $this->provider->id, 'type' => 'payment', 'payment_method' => 'cash', 'amount' => 100, 'transaction_date' => '03/06/2026 12:00'];

        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), $payment)->assertSessionHasErrors(['currency', 'exchange_rate']);
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [...$payment, 'amount' => -1, 'currency' => 'USD', 'exchange_rate' => 1])->assertSessionHasErrors('amount');
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [...$payment, 'currency' => 'GBP', 'exchange_rate' => 1])->assertSessionHasErrors('currency');
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [...$payment, 'currency' => 'USD', 'exchange_rate' => 0])->assertSessionHasErrors('exchange_rate');
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [...$payment, 'currency' => 'USD', 'exchange_rate' => -1])->assertSessionHasErrors('exchange_rate');
        $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [...$payment, 'currency' => 'TJS', 'exchange_rate' => 99, 'notes' => 'Ordinary note'])->assertRedirect(route('admin.provider-ledgers.index'));
        $this->assertDatabaseHas('provider_ledgers', ['amount' => 100, 'notes' => 'Ordinary note']);
    }
}
