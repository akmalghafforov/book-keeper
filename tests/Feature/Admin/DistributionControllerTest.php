<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PotentialDuplicateDetector;
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
            'transaction_date' => '2026-01-15 00:00:00',
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
            'transaction_date' => '2026-02-20 00:00:00',
            'reference_id' => $distribution->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.distributions.store'), []);

        $response->assertSessionHasErrors(['client_id', 'product_id', 'quantity_unit', 'quantity', 'price', 'distribution_date']);
    }

    public function test_index_exposes_potential_duplicate_groups(): void
    {
        Distribution::factory()->create([
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'subtotal' => 500,
            'distribution_date' => '2026-01-15',
        ]);

        Distribution::factory()->create([
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'subtotal' => 500,
            'distribution_date' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.distributions.index'));

        $response
            ->assertOk()
            ->assertSee('Potential Duplicate Distributions')
            ->assertViewHas('potentialDuplicateGroups', function ($groups) {
                return $groups->count() === 1
                    && $groups->first()['count'] === 2
                    && $groups->first()['confidence'] === 'high';
            });
    }

    public function test_resolve_potential_duplicate_hides_false_positive_group(): void
    {
        $firstDistribution = Distribution::factory()->create([
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'subtotal' => 500,
            'distribution_date' => '2026-01-15',
        ]);

        $secondDistribution = Distribution::factory()->create([
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'subtotal' => 500,
            'distribution_date' => '2026-01-15',
        ]);

        $resolutionResponse = $this->actingAs($this->user)
            ->from(route('admin.distributions.index'))
            ->post(route('admin.distributions.potential-duplicates.resolve'), [
                'record_ids' => [$firstDistribution->id, $secondDistribution->id],
            ]);

        $resolutionResponse
            ->assertRedirect(route('admin.distributions.index'))
            ->assertSessionHas('success', 'Potential duplicate group marked as resolved.');

        $this->assertDatabaseHas('potential_duplicate_resolutions', [
            'context' => PotentialDuplicateDetector::CONTEXT_DISTRIBUTION,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.index'))
            ->assertOk()
            ->assertDontSee('Potential Duplicate Distributions')
            ->assertViewHas('potentialDuplicateGroups', fn ($groups) => $groups->isEmpty());
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
            'transaction_date' => '2026-01-15 00:00:00',
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
