<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\DebtLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_debt_with_charges_only(): void
    {
        $client = Client::factory()->create();

        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 1000.00,
        ]);

        $this->assertEquals(1000.00, $client->total_debt);
    }

    public function test_total_debt_with_charges_and_payments(): void
    {
        $client = Client::factory()->create();

        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 1000.00,
        ]);

        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 300.00,
        ]);

        $this->assertEquals(700.00, $client->total_debt);
    }

    public function test_total_debt_with_charges_payments_and_credit_notes(): void
    {
        $client = Client::factory()->create();

        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 1000.00,
        ]);

        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 300.00,
        ]);

        DebtLedger::factory()->creditNote()->create([
            'client_id' => $client->id,
            'amount' => 200.00,
        ]);

        // 1000 - 300 - 200 = 500
        $this->assertEquals(500.00, $client->total_debt);
    }

    public function test_total_debt_returns_zero_when_no_ledger_entries(): void
    {
        $client = Client::factory()->create();

        $this->assertEquals(0.0, $client->total_debt);
    }

    public function test_scope_with_balance_calculates_correctly(): void
    {
        $client = Client::factory()->create();

        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 500.00,
        ]);

        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 200.00,
        ]);

        $result = Client::withBalance()->find($client->id);

        $this->assertEquals(500.00, (float) $result->total_charges);
        $this->assertEquals(200.00, (float) $result->total_payments);
        $this->assertEquals(300.00, (float) $result->balance);
    }

    public function test_scope_with_balance_handles_null_sums(): void
    {
        $client = Client::factory()->create();

        $result = Client::withBalance()->find($client->id);

        $this->assertEquals(0, (float) $result->balance);
    }
}
