<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_create_page_lists_providers(): void
    {
        Provider::factory()->create(['name' => 'North Cement']);

        $response = $this->actingAs($this->user)->get(route('admin.products.create'));

        $response
            ->assertOk()
            ->assertSee('Default Provider')
            ->assertSee('Buy Price')
            ->assertSee('North Cement');
    }

    public function test_store_creates_product_with_default_provider_and_buy_price(): void
    {
        $provider = Provider::factory()->create();

        $response = $this->actingAs($this->user)->post(route('admin.products.store'), [
            'name' => 'Cement M-500',
            'default_unit' => 'per_ton',
            'default_provider_id' => $provider->id,
            'buy_price' => '125.7500',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Cement M-500',
            'default_unit' => 'per_ton',
            'default_provider_id' => $provider->id,
            'buy_price' => '125.7500',
        ]);
    }

    public function test_update_changes_default_provider_and_buy_price(): void
    {
        $oldProvider = Provider::factory()->create();
        $newProvider = Provider::factory()->create();
        $product = Product::factory()->create([
            'default_provider_id' => $oldProvider->id,
            'buy_price' => '100.0000',
        ]);

        $response = $this->actingAs($this->user)->put(route('admin.products.update', $product), [
            'name' => 'Updated Cement',
            'default_unit' => 'per_bag',
            'default_provider_id' => $newProvider->id,
            'buy_price' => '130.1250',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Cement',
            'default_unit' => 'per_bag',
            'default_provider_id' => $newProvider->id,
            'buy_price' => '130.1250',
        ]);
    }

    public function test_update_can_clear_default_provider(): void
    {
        $provider = Provider::factory()->create();
        $product = Product::factory()->create([
            'default_provider_id' => $provider->id,
        ]);

        $response = $this->actingAs($this->user)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'default_unit' => $product->default_unit,
            'default_provider_id' => null,
            'buy_price' => $product->buy_price,
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'default_provider_id' => null,
        ]);
    }

    public function test_store_rejects_deleted_default_provider(): void
    {
        $provider = Provider::factory()->create();
        $provider->delete();

        $response = $this->actingAs($this->user)->post(route('admin.products.store'), [
            'name' => 'Cement M-500',
            'default_unit' => 'per_ton',
            'default_provider_id' => $provider->id,
        ]);

        $response->assertSessionHasErrors(['default_provider_id']);
    }

    public function test_store_rejects_negative_buy_price(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.products.store'), [
            'name' => 'Cement M-500',
            'default_unit' => 'per_ton',
            'buy_price' => '-1',
        ]);

        $response->assertSessionHasErrors(['buy_price']);
    }
}
