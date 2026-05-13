<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_index_shows_client_balance_after_each_operation(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 125,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-01 10:00:00');
        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 30,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 50,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow();

        $response = $this->actingAs($user)->get(route('admin.operations.index'));

        $response->assertOk();
        $response->assertSee(__('Balance'));
        $response->assertSeeInOrder(['145.00', '95.00', '125.00']);
    }
}
