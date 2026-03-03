<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_clients_are_sorted_by_latest_debt_ledger_update()
    {
        // Create three clients
        $client1 = Client::create(['name' => 'Client 1', 'phone' => '111']);
        $client2 = Client::create(['name' => 'Client 2', 'phone' => '222']);
        $client3 = Client::create(['name' => 'Client 3', 'phone' => '333']);

        // Client 2 gets a ledger entry today
        DebtLedger::create([
            'client_id' => $client2->id,
            'amount' => 100,
            'type' => 'charge',
            'updated_at' => now(),
        ]);

        // Client 1 gets a ledger entry yesterday
        DebtLedger::create([
            'client_id' => $client1->id,
            'amount' => 50,
            'type' => 'charge',
            'updated_at' => now()->subDay(),
        ]);

        // Client 3 has no ledger entries, so it should be sorted by its creation date (which is now)
        // But since we want to test that Client 2 is first, let's make sure Client 3 was created earlier
        $client3->created_at = now()->subDays(2);
        $client3->save();

        // Expected order: Client 2 (now), Client 1 (1 day ago), Client 3 (2 days ago)
        
        $response = $this->actingAs($this->user)->get(route('admin.clients.index'));

        $response->assertStatus(200);
        
        $response->assertSeeInOrder([
            'Client 2',
            'Client 1',
            'Client 3',
        ]);
    }
}
