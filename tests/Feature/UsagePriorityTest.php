<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsagePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_recalculates_usage_for_active_catalogs_in_the_inclusive_three_month_window(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 5, 12, 0, 0, config('app.timezone')));

        try {
            $popular = ProductCategory::factory()->create(['name' => 'Popular', 'usage_priority' => 99]);
            $unused = ProductCategory::factory()->create(['name' => 'Unused', 'usage_priority' => 99]);
            $product = Product::factory()->create(['product_category_id' => $popular->id, 'usage_priority' => 99]);
            $otherProduct = Product::factory()->create(['product_category_id' => $popular->id]);
            $unusedProduct = Product::factory()->create(['product_category_id' => $unused->id, 'usage_priority' => 99]);
            $client = Client::factory()->create();
            $supplier = Supplier::factory()->create();

            foreach (['2026-06-05', '2026-09-05'] as $date) {
                Distribution::factory()->create([
                    'client_id' => $client->id,
                    'supplier_id' => $supplier->id,
                    'product_id' => $product->id,
                    'distribution_date' => $date,
                ]);
            }
            Distribution::factory()->create([
                'client_id' => $client->id, 'supplier_id' => $supplier->id,
                'product_id' => $otherProduct->id, 'distribution_date' => '2026-08-01',
            ]);

            Distribution::factory()->create([
                'client_id' => $client->id, 'supplier_id' => $supplier->id,
                'product_id' => $product->id, 'distribution_date' => '2026-06-04',
            ]);
            Distribution::factory()->create([
                'client_id' => $client->id, 'supplier_id' => $supplier->id,
                'product_id' => $product->id, 'distribution_date' => '2026-09-06',
            ]);
            $deleted = Distribution::factory()->create([
                'client_id' => $client->id, 'supplier_id' => $supplier->id,
                'product_id' => $product->id, 'distribution_date' => '2026-08-01',
            ]);
            $deleted->delete();

            $this->artisan('priorities:recalculate-usage')->assertSuccessful();

            $this->assertSame(2, $product->fresh()->usage_priority);
            $this->assertSame(1, $otherProduct->fresh()->usage_priority);
            $this->assertSame(0, $unusedProduct->fresh()->usage_priority);
            $this->assertSame(3, $popular->fresh()->usage_priority);
            $this->assertSame(0, $unused->fresh()->usage_priority);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_catalog_views_use_priority_then_deterministic_name_and_id_ordering(): void
    {
        $user = User::factory()->create();
        $first = ProductCategory::factory()->create(['name' => 'Alpha', 'usage_priority' => 3]);
        $second = ProductCategory::factory()->create(['name' => 'Bravo', 'usage_priority' => 3]);
        $last = ProductCategory::factory()->create(['name' => 'Aardvark', 'usage_priority' => 0]);
        $high = Product::factory()->create(['name' => 'Zulu', 'product_category_id' => $first->id, 'usage_priority' => 4]);
        $alpha = Product::factory()->create(['name' => 'Same', 'product_category_id' => $first->id, 'usage_priority' => 2]);
        $bravo = Product::factory()->create(['name' => 'Same', 'product_category_id' => $first->id, 'usage_priority' => 2]);
        Product::factory()->create(['name' => 'Hidden', 'product_category_id' => $first->id, 'usage_priority' => 99])->delete();

        $this->actingAs($user)->get(route('admin.product-categories.products', $first))
            ->assertOk()
            ->assertJsonPath('0.id', $high->id)
            ->assertJsonPath('1.id', $alpha->id)
            ->assertJsonPath('2.id', $bravo->id);

        $this->actingAs($user)->get(route('admin.distributions.create'))
            ->assertOk()
            ->assertViewHas('productCategories', fn ($categories) => $categories->pluck('id')->take(2)->all() === [$first->id, $second->id]);

        $this->actingAs($user)->get(route('admin.distributions.edit', Distribution::factory()->create([
            'product_id' => $high->id,
        ])))
            ->assertOk()
            ->assertViewHas('productCategories', fn ($categories) => $categories->pluck('id')->take(2)->all() === [$first->id, $second->id]);
    }

    public function test_catalog_indexes_display_usage_priorities(): void
    {
        $category = ProductCategory::factory()->create(['usage_priority' => 7]);
        $product = Product::factory()->create(['product_category_id' => $category->id, 'usage_priority' => 4]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Usage Priority')
            ->assertSee((string) $product->usage_priority);

        $this->actingAs($user)->get(route('admin.product-categories.index'))
            ->assertOk()
            ->assertSee('Usage Priority')
            ->assertSee((string) $category->usage_priority);
    }

    public function test_recalculation_command_is_scheduled_daily_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command, 'priorities:recalculate-usage'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertNotNull($event->withoutOverlapping);
    }
}
