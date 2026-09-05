<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\User;
use App\Services\PotentialDuplicateDetector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ---------------------------------------------------------------
    // Store
    // ---------------------------------------------------------------

    public function test_current_date_selector_sets_the_default_for_new_debt_ledger_entries(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 16, 45, 0, config('app.timezone')));

        try {
            $this->actingAs($this->user)
                ->post(route('set-current-date'), ['current_date' => '2026-07-23'])
                ->assertRedirect();

            $this->actingAs($this->user)
                ->get(route('admin.debt-ledgers.create'))
                ->assertOk()
                ->assertSee('value="23/7/2026"', false)
                ->assertSee("defaultDate: '23/7/2026'", false)
                ->assertSee('<option value="cash" selected>cash</option>', false)
                ->assertSee('name="currency"', false)
                ->assertSee('<option value="TJS" selected>TJS</option>', false)
                ->assertSee('<option value="USD"', false)
                ->assertSee('<option value="EUR"', false)
                ->assertSee('<option value="UZS"', false)
                ->assertSee('<option value="RUB"', false)
                ->assertSee('value="1"', false)
                ->assertSee(__('Converted amount'), false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_old_transaction_date_overrides_the_current_date_default(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 16, 45, 0, config('app.timezone')));

        try {
            $this->actingAs($this->user)
                ->from(route('admin.debt-ledgers.create'))
                ->post(route('admin.debt-ledgers.store'), [
                    'transaction_date' => '01/01/2020',
                ])
                ->assertRedirect(route('admin.debt-ledgers.create'));

            $this->actingAs($this->user)
                ->get(route('admin.debt-ledgers.create'))
                ->assertOk()
                ->assertSee("defaultDate: '01/01/2020'", false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_store_creates_payment_ledger_entry(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'amount' => 250.50,
            'currency' => 'TJS',
            'exchange_rate' => 1,
            'transaction_date' => '10/03/2026',
            'notes' => 'Cash payment received',
        ]);

        $response->assertRedirect(route('admin.debt-ledgers.index'));

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'amount' => 250.50,
            'transaction_date' => '2026-03-10 00:00:00',
            'notes' => 'Cash payment received',
        ]);
    }

    public function test_store_creates_charge_ledger_entry(): void
    {
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            'client_id' => $this->client->id,
            'type' => 'charge',
            'amount' => 100.00,
            'transaction_date' => '11/03/2026',
            'notes' => 'Manual charge',
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $this->client->id,
            'type' => 'charge',
            'amount' => 100.00,
            'transaction_date' => '2026-03-11 00:00:00',
        ]);
    }

    public function test_store_creates_credit_note_ledger_entry(): void
    {
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            'client_id' => $this->client->id,
            'type' => 'credit_note',
            'amount' => 75.00,
            'transaction_date' => '12/03/2026',
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $this->client->id,
            'type' => 'credit_note',
            'amount' => 75.00,
            'transaction_date' => '2026-03-12 00:00:00',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), []);

        $response->assertSessionHasErrors(['client_id', 'type', 'amount', 'transaction_date']);
    }

    public function test_store_validates_minimum_amount(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'amount' => 0,
            'transaction_date' => '10/03/2026',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_payment_method_is_required_and_accepts_card_for_payments(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'amount' => 100,
            'currency' => 'TJS',
            'exchange_rate' => 1,
            'transaction_date' => '10/03/2026',
        ];

        $this->actingAs($this->user)
            ->post(route('admin.debt-ledgers.store'), $payload)
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($this->user)
            ->post(route('admin.debt-ledgers.store'), [...$payload, 'payment_method' => 'invalid'])
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($this->user)
            ->post(route('admin.debt-ledgers.store'), [...$payload, 'payment_method' => 'card'])
            ->assertRedirect(route('admin.debt-ledgers.index'));

        $this->assertDatabaseHas('debt_ledgers', ['payment_method' => 'card']);
    }

    public function test_non_payment_entries_clear_a_submitted_payment_method(): void
    {
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            'client_id' => $this->client->id,
            'type' => 'credit_note',
            'payment_method' => 'alif',
            'amount' => 75,
            'transaction_date' => '10/03/2026',
        ])->assertRedirect(route('admin.debt-ledgers.index'));

        $this->assertDatabaseHas('debt_ledgers', ['type' => 'credit_note', 'payment_method' => null]);
    }

    public function test_index_exposes_potential_duplicate_groups(): void
    {
        DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'amount' => 250.50,
            'transaction_date' => '2026-03-10',
            'notes' => 'Cash payment received',
        ]);

        DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'amount' => 250.50,
            'transaction_date' => '2026-03-10',
            'notes' => 'Cash payment received',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.debt-ledgers.index'));

        $response
            ->assertOk()
            ->assertSee('Potential Duplicate Entries')
            ->assertViewHas('potentialDuplicateGroups', function ($groups) {
                return $groups->count() === 1
                    && $groups->first()['count'] === 2
                    && $groups->first()['confidence'] === 'high';
            });
    }

    public function test_resolve_potential_duplicate_hides_false_positive_group(): void
    {
        $firstLedger = DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'amount' => 250.50,
            'transaction_date' => '2026-03-10',
            'notes' => 'Cash payment received',
        ]);

        $secondLedger = DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'amount' => 250.50,
            'transaction_date' => '2026-03-10',
            'notes' => 'Cash payment received',
        ]);

        $resolutionResponse = $this->actingAs($this->user)
            ->from(route('admin.debt-ledgers.index'))
            ->post(route('admin.debt-ledgers.potential-duplicates.resolve'), [
                'record_ids' => [$firstLedger->id, $secondLedger->id],
            ]);

        $resolutionResponse
            ->assertRedirect(route('admin.debt-ledgers.index'))
            ->assertSessionHas('success', 'Potential duplicate group marked as resolved.');

        $this->assertDatabaseHas('potential_duplicate_resolutions', [
            'context' => PotentialDuplicateDetector::CONTEXT_DEBT_LEDGER,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.debt-ledgers.index'))
            ->assertOk()
            ->assertDontSee('Potential Duplicate Entries')
            ->assertViewHas('potentialDuplicateGroups', fn ($groups) => $groups->isEmpty());
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    public function test_update_modifies_ledger_entry(): void
    {
        $ledger = DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'amount' => 100.00,
        ]);

        $this->actingAs($this->user)->put(route('admin.debt-ledgers.update', $ledger), [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'payment_method' => 'ds',
            'amount' => 200.00,
            'transaction_date' => '15/03/2026',
            'notes' => 'Updated amount',
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'id' => $ledger->id,
            'amount' => 200.00,
            'transaction_date' => '2026-03-15 00:00:00',
            'notes' => 'Updated amount',
            'payment_method' => 'ds',
        ]);
    }

    public function test_payment_details_show_the_saved_method_and_legacy_entries_show_na(): void
    {
        $ledger = DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'payment_method' => 'alif',
        ]);
        $legacyLedger = DebtLedger::factory()->payment()->create(['client_id' => $this->client->id]);

        $this->actingAs($this->user)->get(route('admin.debt-ledgers.show', $ledger))
            ->assertOk()
            ->assertSee('Payment Method')
            ->assertSee('Алиф');
        $this->actingAs($this->user)->get(route('admin.debt-ledgers.show', $legacyLedger))
            ->assertOk()
            ->assertSee(__('N/A'));
    }

    public function test_updating_a_payment_to_a_charge_clears_its_payment_method(): void
    {
        $ledger = DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($this->user)->put(route('admin.debt-ledgers.update', $ledger), [
            'client_id' => $this->client->id,
            'type' => 'charge',
            'payment_method' => 'alif',
            'amount' => 100,
            'transaction_date' => '15/03/2026',
        ])->assertRedirect(route('admin.debt-ledgers.index'));

        $this->assertDatabaseHas('debt_ledgers', ['id' => $ledger->id, 'payment_method' => null]);
    }

    public function test_payment_purpose_is_optional_and_on_behalf_of_is_persisted_and_cleared(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'amount' => 100,
            'currency' => 'TJS',
            'exchange_rate' => 1,
            'transaction_date' => '10/03/2026',
        ];

        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), $payload)
            ->assertRedirect(route('admin.debt-ledgers.index'));
        $this->assertDatabaseHas('debt_ledgers', ['payment_purpose' => null, 'payer_name' => null]);

        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            ...$payload,
            'payment_purpose' => 'on_behalf_of',
        ])->assertSessionHasErrors('payer_name');

        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            ...$payload,
            'payment_purpose' => 'on_behalf_of',
            'payer_name' => 'Farid',
        ])->assertRedirect(route('admin.debt-ledgers.index'));

        $ledger = DebtLedger::latest('id')->firstOrFail();
        $this->actingAs($this->user)->get(route('admin.debt-ledgers.show', $ledger))
            ->assertOk()
            ->assertSee('Аз номи Farid');
        $this->actingAs($this->user)->get(route('admin.debt-ledgers.index'))
            ->assertOk()
            ->assertSee('Аз номи Farid');

        $this->actingAs($this->user)->put(route('admin.debt-ledgers.update', $ledger), [
            'client_id' => $this->client->id,
            'type' => 'credit_note',
            'payment_purpose' => 'on_behalf_of',
            'payer_name' => 'Farid',
            'amount' => 100,
            'transaction_date' => '10/03/2026',
        ])->assertRedirect(route('admin.debt-ledgers.index'));

        $this->assertDatabaseHas('debt_ledgers', ['id' => $ledger->id, 'payment_purpose' => null, 'payer_name' => null]);
    }

    // ---------------------------------------------------------------
    // Destroy
    // ---------------------------------------------------------------

    public function test_destroy_soft_deletes_ledger_entry(): void
    {
        $ledger = DebtLedger::factory()->payment()->create([
            'client_id' => $this->client->id,
            'amount' => 100.00,
        ]);

        $this->actingAs($this->user)->delete(route('admin.debt-ledgers.destroy', $ledger));

        $this->assertSoftDeleted('debt_ledgers', ['id' => $ledger->id]);
    }

    public function test_foreign_currency_payments_are_converted_and_audited(): void
    {
        $payment = [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'amount' => '100',
            'currency' => 'USD',
            'exchange_rate' => '9.50',
            'transaction_date' => '10/03/2026',
        ];

        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [...$payment, 'notes' => 'Existing note'])
            ->assertRedirect(route('admin.debt-ledgers.index'));
        $this->assertDatabaseHas('debt_ledgers', ['amount' => 950, 'notes' => 'Existing note | 100 USD × 9.50 = 950 TJS']);

        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), $payment)
            ->assertRedirect(route('admin.debt-ledgers.index'));
        $this->assertDatabaseHas('debt_ledgers', ['notes' => '100 USD × 9.50 = 950 TJS']);
    }

    public function test_payment_currency_fields_are_validated_and_tjs_rate_is_forced(): void
    {
        $payment = ['client_id' => $this->client->id, 'type' => 'payment', 'payment_method' => 'cash', 'amount' => 100, 'transaction_date' => '10/03/2026'];

        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), $payment)->assertSessionHasErrors(['currency', 'exchange_rate']);
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [...$payment, 'amount' => -1, 'currency' => 'USD', 'exchange_rate' => 1])->assertSessionHasErrors('amount');
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [...$payment, 'currency' => 'GBP', 'exchange_rate' => 1])->assertSessionHasErrors('currency');
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [...$payment, 'currency' => 'USD', 'exchange_rate' => 0])->assertSessionHasErrors('exchange_rate');
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [...$payment, 'currency' => 'USD', 'exchange_rate' => -1])->assertSessionHasErrors('exchange_rate');
        $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [...$payment, 'currency' => 'TJS', 'exchange_rate' => 99, 'notes' => 'Ordinary note'])->assertRedirect(route('admin.debt-ledgers.index'));
        $this->assertDatabaseHas('debt_ledgers', ['amount' => 100, 'notes' => 'Ordinary note']);
    }
}
