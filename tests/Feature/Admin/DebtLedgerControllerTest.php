<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\User;
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

    public function test_store_creates_payment_ledger_entry(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.debt-ledgers.store'), [
            'client_id' => $this->client->id,
            'type' => 'payment',
            'amount' => 250.50,
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
            'amount' => 200.00,
            'transaction_date' => '15/03/2026',
            'notes' => 'Updated amount',
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'id' => $ledger->id,
            'amount' => 200.00,
            'transaction_date' => '2026-03-15 00:00:00',
            'notes' => 'Updated amount',
        ]);
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
}
