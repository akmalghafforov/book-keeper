<?php

namespace Tests\Feature;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCategoryAssignmentCorrectionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const SNAPSHOT_TABLE = 'product_category_assignment_correction_snapshots';

    /** @var array<string, list<string>> */
    private const CATALOGUE = [
        'Цемент' => [
            'Ёқут М500', 'Ёқут M400', 'Тоҷ Смент', 'Яксин М500', 'Яксин М400',
            'Уқоб M500', 'Уқоб M400', 'Хуаксин M500', 'Хуаксин M400', 'Мохир M400',
            'Мохир M500', 'Хатлон M400', 'Хатлон M500',
        ],
        'Рассыпной' => [
            'Тоҷиксемент Рассыпной М500', 'Рассипной Ганҷ M500', 'Рассипной Ганҷ M400',
            'Портланд Рассипной', 'Рассыпной Мохир M500', 'Рассыпной Хуаксин M500',
        ],
        'Гипсокартон' => [
            'Гипсокартон', 'Гипсакартон Кабуд', 'Гипс. стеновой',
            'Гипс потолочный - простой', 'Гипс потолочный - влагастойкий',
        ],
        'Другое' => ['Шифер', 'ОСБ 0.9', 'Оҳок', 'Арматур', 'ОСБ 0.6'],
    ];

    public function test_it_corrects_catalogue_assignments_without_relying_on_category_ids_and_preserves_unrelated_products(): void
    {
        $migration = $this->resetCorrectionMigration();
        $categories = $this->createCategoriesInNonCanonicalOrder();
        $originalTime = Carbon::create(2026, 9, 1, 8, 0, 0);

        foreach (self::CATALOGUE as $products) {
            foreach ($products as $name) {
                if ($name === 'Яксин М500') {
                    continue;
                }

                Product::factory()->create([
                    'name' => $name,
                    'product_category_id' => $categories['Другое'],
                    'updated_at' => $originalTime,
                ]);
            }
        }
        $deleted = Product::factory()->create([
            'name' => 'Яксин М500',
            'product_category_id' => $categories['Другое'],
            'updated_at' => $originalTime,
        ]);
        // The migration must include matching soft-deleted catalogue products.
        Product::whereKey($deleted->id)->delete();
        $unrelated = Product::factory()->create([
            'name' => 'Unrelated product',
            'product_category_id' => $categories['Другое'],
            'updated_at' => $originalTime,
        ]);

        $migration->up();

        foreach (self::CATALOGUE as $category => $products) {
            $this->assertSame(count($products), DB::table('products')
                ->whereIn('name', $products)
                ->where('product_category_id', $categories[$category])
                ->count());
        }
        $this->assertSame($categories['Другое'], (int) $unrelated->fresh()->product_category_id);
        $this->assertDatabaseHas(self::SNAPSHOT_TABLE, ['product_id' => $deleted->id]);
    }

    public function test_rollback_restores_original_values_and_refuses_to_overwrite_later_edits(): void
    {
        $migration = $this->resetCorrectionMigration();
        $categories = $this->createCategoriesInNonCanonicalOrder();
        $originalTime = Carbon::create(2026, 9, 1, 8, 0, 0);
        $product = Product::factory()->create([
            'name' => 'Ёқут М500',
            'product_category_id' => $categories['Другое'],
            'updated_at' => $originalTime,
        ]);

        $migration->up();
        $migration->down();

        $product->refresh();
        $this->assertSame($categories['Другое'], (int) $product->product_category_id);
        $this->assertSame($originalTime->format('Y-m-d H:i:s'), $product->updated_at->format('Y-m-d H:i:s'));
        $this->assertFalse(Schema::hasTable(self::SNAPSHOT_TABLE));

        $migration->up();
        DB::table('products')->where('id', $product->id)->update(['updated_at' => now()->addMinute()]);

        try {
            $migration->down();
            $this->fail('Rollback should refuse to overwrite a later product edit.');
        } catch (\RuntimeException) {
            // Expected: the correction snapshot must remain available for a safe retry.
        }

        $this->assertTrue(Schema::hasTable(self::SNAPSHOT_TABLE));
    }

    private function resetCorrectionMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_09_05_000007_correct_catalogue_product_category_assignments.php');
        $migration->down();

        return $migration;
    }

    /** @return array<string, int> */
    private function createCategoriesInNonCanonicalOrder(): array
    {
        DB::table('product_categories')->delete();
        foreach (['Другое', 'Гипсокартон', 'Рассыпной', 'Цемент'] as $name) {
            DB::table('product_categories')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }

        return DB::table('product_categories')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
    }
}
