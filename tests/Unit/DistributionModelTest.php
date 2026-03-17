<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionModelTest extends TestCase
{
    use RefreshDatabase;

    private function createDistribution(array $overrides = []): Distribution
    {
        return Distribution::factory()->create($overrides);
    }

    // ---------------------------------------------------------------
    // createDebtLedgerCharge
    // ---------------------------------------------------------------

    public function test_creating_distribution_creates_charge_ledger_entry(): void
    {
        $distribution = $this->createDistribution(['subtotal' => 500.00]);

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $distribution->client_id,
            'type' => 'charge',
            'amount' => 500.00,
            'transaction_date' => $distribution->distribution_date->toDateString() . ' 00:00:00',
            'reference_id' => $distribution->id,
        ]);
    }

    public function test_creating_distribution_with_credit_client_creates_credit_note(): void
    {
        $creditClient = Client::factory()->create();
        $distribution = $this->createDistribution([
            'credit_client_id' => $creditClient->id,
            'subtotal' => 750.00,
        ]);

        // Charge for the main client
        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $distribution->client_id,
            'type' => 'charge',
            'reference_id' => $distribution->id,
        ]);

        // Credit note for the credit client
        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $creditClient->id,
            'type' => 'credit_note',
            'amount' => 750.00,
            'transaction_date' => $distribution->distribution_date->toDateString() . ' 00:00:00',
            'reference_id' => $distribution->id,
        ]);
    }

    public function test_creating_distribution_without_credit_client_does_not_create_credit_note(): void
    {
        $distribution = $this->createDistribution(['credit_client_id' => null]);

        $this->assertDatabaseMissing('debt_ledgers', [
            'type' => 'credit_note',
            'reference_id' => $distribution->id,
        ]);
    }

    // ---------------------------------------------------------------
    // syncDebtLedgerCharge
    // ---------------------------------------------------------------

    public function test_updating_distribution_syncs_charge_amount(): void
    {
        $distribution = $this->createDistribution(['subtotal' => 500.00]);

        $distribution->update([
            'subtotal' => 800.00,
            'distribution_date' => '2026-03-17',
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'charge',
            'amount' => 800.00,
            'transaction_date' => '2026-03-17 00:00:00',
        ]);
    }

    public function test_updating_distribution_syncs_client_change(): void
    {
        $originalClient = Client::factory()->create();
        $newClient = Client::factory()->create();

        $distribution = $this->createDistribution([
            'client_id' => $originalClient->id,
            'subtotal' => 300.00,
        ]);

        $distribution->update(['client_id' => $newClient->id]);

        $this->assertDatabaseHas('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'charge',
            'client_id' => $newClient->id,
        ]);
    }

    public function test_updating_distribution_to_add_credit_client_creates_credit_note(): void
    {
        $distribution = $this->createDistribution([
            'credit_client_id' => null,
            'subtotal' => 400.00,
        ]);

        $creditClient = Client::factory()->create();
        $distribution->update(['credit_client_id' => $creditClient->id]);

        $this->assertDatabaseHas('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'credit_note',
            'client_id' => $creditClient->id,
            'amount' => 400.00,
            'transaction_date' => $distribution->fresh()->distribution_date->toDateString() . ' 00:00:00',
        ]);
    }

    public function test_updating_distribution_to_remove_credit_client_deletes_credit_note(): void
    {
        $creditClient = Client::factory()->create();
        $distribution = $this->createDistribution([
            'credit_client_id' => $creditClient->id,
            'subtotal' => 400.00,
        ]);

        // Confirm credit note exists
        $this->assertDatabaseHas('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'credit_note',
        ]);

        $distribution->update(['credit_client_id' => null]);

        $this->assertDatabaseMissing('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'credit_note',
            'deleted_at' => null,
        ]);
    }

    public function test_sync_creates_charge_if_ledger_missing(): void
    {
        $distribution = $this->createDistribution(['subtotal' => 500.00]);

        // Manually delete the ledger so sync recreates it
        DebtLedger::where('reference_id', $distribution->id)->where('type', 'charge')->forceDelete();

        $distribution->syncDebtLedgerCharge();

        $this->assertDatabaseHas('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'charge',
            'amount' => 500.00,
            'transaction_date' => $distribution->distribution_date->toDateString() . ' 00:00:00',
        ]);
    }

    // ---------------------------------------------------------------
    // deleteDebtLedgerCharge
    // ---------------------------------------------------------------

    public function test_deleting_distribution_deletes_charge_ledger_entry(): void
    {
        $distribution = $this->createDistribution(['subtotal' => 600.00]);
        $distributionId = $distribution->id;

        $distribution->delete();

        $this->assertDatabaseMissing('debt_ledgers', [
            'reference_id' => $distributionId,
            'type' => 'charge',
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_distribution_deletes_both_charge_and_credit_note(): void
    {
        $creditClient = Client::factory()->create();
        $distribution = $this->createDistribution([
            'credit_client_id' => $creditClient->id,
            'subtotal' => 600.00,
        ]);
        $distributionId = $distribution->id;

        $distribution->delete();

        $remaining = DebtLedger::where('reference_id', $distributionId)
            ->whereIn('type', ['charge', 'credit_note'])
            ->count();

        $this->assertEquals(0, $remaining);
    }

    // ---------------------------------------------------------------
    // restoreDebtLedgerCharge
    // ---------------------------------------------------------------

    public function test_restoring_distribution_restores_ledger_entries(): void
    {
        $creditClient = Client::factory()->create();
        $distribution = $this->createDistribution([
            'credit_client_id' => $creditClient->id,
            'subtotal' => 700.00,
        ]);
        $distributionId = $distribution->id;

        $distribution->delete();

        // Confirm soft-deleted
        $this->assertEquals(0, DebtLedger::where('reference_id', $distributionId)->count());

        $distribution->restore();

        // Both charge and credit_note should be restored
        $this->assertEquals(2, DebtLedger::where('reference_id', $distributionId)->count());
    }
}
