<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->product = Product::factory()->create();
        $this->supplier = Supplier::factory()->create();
    }

    public function test_store_creates_distribution_and_charge_ledger(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.distributions.store'), [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'distribution_date' => '15/01/2026',
        ]);

        $response->assertRedirect(route('admin.distributions.index'));

        $this->assertDatabaseHas('distributions', [
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'subtotal' => 500.0,
        ]);

        $distribution = Distribution::latest('id')->first();

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $this->client->id,
            'type' => 'charge',
            'amount' => 500.0,
            'reference_id' => $distribution->id,
        ]);
    }

    public function test_store_with_credit_client_creates_credit_note(): void
    {
        $creditClient = Client::factory()->create();

        $this->actingAs($this->user)->post(route('admin.distributions.store'), [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'credit_client_id' => $creditClient->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_bag',
            'quantity' => 5,
            'price' => 100,
            'distribution_date' => '20/02/2026',
        ]);

        $distribution = Distribution::latest('id')->first();
        $this->assertNotNull($distribution, 'Distribution should have been created');

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $creditClient->id,
            'type' => 'credit_note',
            'amount' => 500.0,
            'reference_id' => $distribution->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.distributions.store'), []);

        $response->assertSessionHasErrors(['client_id', 'product_id', 'quantity_unit', 'quantity', 'price', 'distribution_date']);
    }

    public function test_update_syncs_ledger_amount(): void
    {
        $distribution = Distribution::factory()->create([
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'subtotal' => 500.00,
            'distribution_date' => '2026-01-15',
        ]);

        $this->actingAs($this->user)->put(route('admin.distributions.update', $distribution), [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 20,
            'price' => 50,
            'distribution_date' => '15/01/2026',
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'reference_id' => $distribution->id,
            'type' => 'charge',
            'amount' => 1000.0,
        ]);
    }

    public function test_destroy_soft_deletes_distribution_and_ledger(): void
    {
        $distribution = Distribution::factory()->create([
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'subtotal' => 300.00,
        ]);
        $distributionId = $distribution->id;

        $this->actingAs($this->user)->delete(route('admin.distributions.destroy', $distribution));

        $this->assertSoftDeleted('distributions', ['id' => $distributionId]);

        $this->assertDatabaseMissing('debt_ledgers', [
            'reference_id' => $distributionId,
            'deleted_at' => null,
        ]);
    }
}
