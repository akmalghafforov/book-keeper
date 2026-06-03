<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\User;
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
            ->assertSee('North Cement')
            ->assertSee('AA-1234')
            ->assertSee('payment')
            ->assertSee(route('admin.reports.export-provider-ledger-debt', $chargeLedger), false)
            ->assertSee('25.0000');
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
            ->assertSee('North Cement');
    }

    public function test_store_creates_provider_payment(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '25.2500',
            'transaction_date' => '03/06/2026',
            'notes' => 'Cash payment',
        ]);

        $response->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '25.2500',
            'transaction_date' => '2026-06-03 00:00:00',
            'notes' => 'Cash payment',
        ]);
    }

    public function test_store_creates_provider_debt(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'charge',
            'amount' => '125.7500',
            'car_number' => 'AA-1234',
            'transaction_date' => '03/06/2026',
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
            'notes' => 'Manual supplier debt',
        ]);
    }

    public function test_store_validates_positive_amount(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.store'), [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '0',
            'transaction_date' => '03/06/2026',
        ]);

        $response->assertSessionHasErrors(['amount']);
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

    public function test_update_modifies_provider_payment(): void
    {
        $ledger = ProviderLedger::factory()->payment()->create([
            'provider_id' => $this->provider->id,
            'amount' => '25.0000',
        ]);

        $response = $this->actingAs($this->user)->put(route('admin.provider-ledgers.update', $ledger), [
            'provider_id' => $this->provider->id,
            'type' => 'payment',
            'amount' => '30.5000',
            'transaction_date' => '04/06/2026',
            'notes' => 'Updated payment',
        ]);

        $response->assertRedirect(route('admin.provider-ledgers.index'));

        $this->assertDatabaseHas('provider_ledgers', [
            'id' => $ledger->id,
            'type' => 'payment',
            'amount' => '30.5000',
            'transaction_date' => '2026-06-04 00:00:00',
            'notes' => 'Updated payment',
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
            'amount' => '130.7500',
            'car_number' => 'BB-5678',
            'transaction_date' => '04/06/2026',
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
            'notes' => 'Updated manual debt',
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
                'transaction_date' => '04/06/2026',
            ])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete(route('admin.provider-ledgers.destroy', $ledger))
            ->assertForbidden();
    }
}
