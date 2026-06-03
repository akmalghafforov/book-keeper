<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_index_lists_providers(): void
    {
        Provider::factory()->create(['name' => 'North Cement']);
        Provider::factory()->create(['name' => 'East Aggregates']);

        $response = $this->actingAs($this->user)->get(route('admin.providers.index'));

        $response
            ->assertOk()
            ->assertSee('North Cement')
            ->assertSee('East Aggregates');
    }

    public function test_store_creates_provider(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.providers.store'), [
            'name' => 'Central Provider',
            'phone' => '+992 111 222 333',
            'address' => 'Dushanbe',
            'notes' => 'Preferred cash terms',
        ]);

        $response->assertRedirect(route('admin.providers.index'));

        $this->assertDatabaseHas('providers', [
            'name' => 'Central Provider',
            'phone' => '+992 111 222 333',
            'address' => 'Dushanbe',
            'notes' => 'Preferred cash terms',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.providers.store'), [
            'phone' => '+992 111 222 333',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_changes_provider(): void
    {
        $provider = Provider::factory()->create([
            'name' => 'Old Provider',
        ]);

        $response = $this->actingAs($this->user)->put(route('admin.providers.update', $provider), [
            'name' => 'Updated Provider',
            'phone' => '+992 999 888 777',
            'address' => 'Khujand',
            'notes' => 'Updated notes',
        ]);

        $response->assertRedirect(route('admin.providers.index'));

        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'name' => 'Updated Provider',
            'phone' => '+992 999 888 777',
            'address' => 'Khujand',
            'notes' => 'Updated notes',
        ]);
    }

    public function test_destroy_soft_deletes_provider(): void
    {
        $provider = Provider::factory()->create();
        $product = Product::factory()->create([
            'default_provider_id' => $provider->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('admin.providers.destroy', $provider));

        $response->assertRedirect(route('admin.providers.index'));
        $this->assertSoftDeleted('providers', ['id' => $provider->id]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'default_provider_id' => null,
        ]);
    }
}
