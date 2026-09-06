<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Provider;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PotentialDuplicateDetector;
use Carbon\Carbon;
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

    public function test_create_uses_the_selected_current_date_for_distribution_defaults(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 16, 45, 0, config('app.timezone')));

        try {
            $this->actingAs($this->user)
                ->withSession(['current_date' => '2026-07-23'])
                ->get(route('admin.distributions.create'))
                ->assertOk()
                ->assertSee("defaultDate: '23/7/2026'", false)
                ->assertSee("defaultDate: '23/7/2026 16:45'", false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_create_date_picker_is_focused_without_opening_and_uses_keyboard_date_controls(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertSee('clickOpens: false', false)
            ->assertSee("if (event.key === 'Enter')", false)
            ->assertSee("['ArrowLeft', 'ArrowRight'].includes(event.key)", false)
            ->assertSee("setTimeout(() => input.focus(), 0)", false);
    }

    public function test_create_exposes_provider_availability_and_conditionally_disables_provider_fields(): void
    {
        $provider = Provider::factory()->create();
        $this->product->update(['default_provider_id' => $provider->id]);
        Product::factory()->create(['default_provider_id' => null]);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertSee('has_default_provider', false)
            ->assertSee('x-show="hasDefaultProvider"', false)
            ->assertSee(':disabled="!hasDefaultProvider"', false)
            ->assertViewHas('products', fn ($products) => $products->contains($this->product)
                && $products->contains(fn (Product $product) => $product->default_provider_id === null));
    }

    public function test_create_defaults_to_the_highest_positive_usage_priority_category(): void
    {
        $lessUsed = ProductCategory::factory()->create(['name' => 'Alpha', 'usage_priority' => 2]);
        $mostUsed = ProductCategory::factory()->create(['name' => 'Zulu', 'usage_priority' => 3]);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertViewHas('defaultProductCategoryId', $mostUsed->id)
            ->assertSee("productCategoryId: {$mostUsed->id}", false);

        $this->assertNotSame($lessUsed->id, $mostUsed->id);
    }

    public function test_create_defaults_to_the_highest_positive_usage_priority_product(): void
    {
        $lessUsedCategory = ProductCategory::factory()->create(['usage_priority' => 5]);
        $mostUsedCategory = ProductCategory::factory()->create(['usage_priority' => 1]);
        Product::factory()->create([
            'product_category_id' => $lessUsedCategory->id,
            'usage_priority' => 2,
        ]);
        $mostUsed = Product::factory()->create([
            'product_category_id' => $mostUsedCategory->id,
            'usage_priority' => 3,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertViewHas('defaultProduct', fn (?Product $product) => $product?->is($mostUsed))
            ->assertViewHas('defaultProductCategoryId', $mostUsedCategory->id)
            ->assertViewHas('initialProductId', $mostUsed->id)
            ->assertSee("productId: {$mostUsed->id}", false)
            ->assertSee($mostUsed->name, false);
    }

    public function test_create_uses_existing_category_ordering_to_break_usage_priority_ties(): void
    {
        $first = ProductCategory::factory()->create(['name' => 'Alpha', 'usage_priority' => 3]);
        ProductCategory::factory()->create(['name' => 'Bravo', 'usage_priority' => 3]);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertViewHas('defaultProductCategoryId', $first->id);
    }

    public function test_create_leaves_category_unselected_when_all_usage_priorities_are_zero(): void
    {
        ProductCategory::factory()->create(['usage_priority' => 0]);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertViewHas('defaultProductCategoryId', null)
            ->assertSee('productCategoryId: null', false);
    }

    public function test_create_preserves_old_category_input_over_the_computed_default(): void
    {
        $default = ProductCategory::factory()->create(['usage_priority' => 3]);
        $selected = ProductCategory::factory()->create(['usage_priority' => 1]);

        $this->actingAs($this->user)
            ->post(route('admin.distributions.store'), ['product_category_id' => $selected->id])
            ->assertSessionHasErrors(['client_id']);

        $this->actingAs($this->user)
            ->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertViewHas('defaultProductCategoryId', $default->id)
            ->assertSee("productCategoryId: {$selected->id}", false);
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

    public function test_store_saves_provider_received_at_and_syncs_provider_ledger(): void
    {
        $provider = Provider::factory()->create();
        $this->product->update([
            'default_provider_id' => $provider->id,
            'buy_price' => '12.5000',
        ]);

        $response = $this->actingAs($this->user)->post(route('admin.distributions.store'), [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'distribution_date' => '15/01/2026',
            'provider_received_at' => '15/01/2026 14:30',
        ]);

        $response->assertRedirect(route('admin.distributions.index'));

        $distribution = Distribution::latest('id')->firstOrFail();

        $this->assertDatabaseHas('distributions', [
            'id' => $distribution->id,
            'provider_received_at' => '2026-01-15 14:30:00',
        ]);

        $this->assertDatabaseHas('provider_ledgers', [
            'provider_id' => $provider->id,
            'distribution_id' => $distribution->id,
            'transaction_date' => '2026-01-15 00:00:00',
            'provider_received_at' => '2026-01-15 14:30:00',
            'amount' => '125.0000',
        ]);
    }

    public function test_store_discards_provider_values_for_a_product_without_a_default_provider(): void
    {
        $this->product->update(['default_provider_id' => null]);

        $response = $this->actingAs($this->user)->post(route('admin.distributions.store'), [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'distribution_date' => '15/01/2026',
            'provider_buy_price' => 12.5,
            'provider_received_at' => '15/01/2026 14:30',
        ]);

        $response->assertRedirect(route('admin.distributions.index'));

        $this->assertDatabaseHas('distributions', [
            'product_id' => $this->product->id,
            'provider_buy_price' => null,
            'provider_received_at' => null,
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
            'credit_client_price' => 80,
            'distribution_date' => '20/02/2026',
        ]);

        $distribution = Distribution::latest('id')->first();
        $this->assertNotNull($distribution, 'Distribution should have been created');

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $creditClient->id,
            'type' => 'credit_note',
            'amount' => 400.0,
            'transaction_date' => '2026-02-20 00:00:00',
            'reference_id' => $distribution->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.distributions.store'), []);

        $response->assertSessionHasErrors(['client_id', 'product_id', 'quantity_unit', 'quantity', 'price', 'distribution_date']);
    }

    public function test_store_requires_a_non_negative_credit_client_price_when_credit_client_is_selected(): void
    {
        $creditClient = Client::factory()->create();
        $data = [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'credit_client_id' => $creditClient->id,
            'product_id' => $this->product->id,
            'quantity_unit' => 'per_ton',
            'quantity' => 10,
            'price' => 50,
            'distribution_date' => '15/01/2026',
        ];

        $this->actingAs($this->user)
            ->post(route('admin.distributions.store'), $data)
            ->assertSessionHasErrors('credit_client_price');

        $this->actingAs($this->user)
            ->post(route('admin.distributions.store'), [...$data, 'credit_client_price' => -1])
            ->assertSessionHasErrors('credit_client_price');
    }

    public function test_updating_credit_client_price_resyncs_only_the_credit_note(): void
    {
        $creditClient = Client::factory()->create();
        $distribution = Distribution::factory()->create([
            'client_id' => $this->client->id,
            'credit_client_id' => $creditClient->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 5,
            'price' => 100,
            'credit_client_price' => 80,
            'subtotal' => 500,
            'distribution_date' => '2026-01-15',
        ]);

        $this->actingAs($this->user)->put(route('admin.distributions.update', $distribution), [
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'credit_client_id' => $creditClient->id,
            'credit_client_price' => 70,
            'product_id' => $this->product->id,
            'quantity_unit' => $distribution->quantity_unit,
            'quantity' => 5,
            'price' => 100,
            'distribution_date' => '15/01/2026',
        ]);

        $this->assertDatabaseHas('debt_ledgers', ['reference_id' => $distribution->id, 'type' => 'charge', 'amount' => 500.0]);
        $this->assertDatabaseHas('debt_ledgers', ['reference_id' => $distribution->id, 'type' => 'credit_note', 'amount' => 350.0]);
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
