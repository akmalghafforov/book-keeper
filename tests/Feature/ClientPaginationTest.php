<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagination_with_debt_status_filter()
    {
        $user = User::factory()->create();

        $clientWithDebt = Client::create(['name' => 'Client With Debt', 'phone' => '111111']);
        DebtLedger::create([
            'client_id' => $clientWithDebt->id,
            'amount' => 100,
            'type' => 'charge',
            'date' => now(),
        ]);

        $clientNoDebt = Client::create(['name' => 'Client No Debt', 'phone' => '222222']);
        DebtLedger::create([
            'client_id' => $clientNoDebt->id,
            'amount' => 100,
            'type' => 'payment',
            'date' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.clients.index', ['debt_status' => 'with_debt']));

        $response->assertStatus(200);
        
        $clients = $response->viewData('clients');
        $this->assertTrue($clients->contains('id', $clientWithDebt->id));
        $this->assertFalse($clients->contains('id', $clientNoDebt->id));
    }
}
