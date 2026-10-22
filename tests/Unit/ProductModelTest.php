<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_product_without_category_assigns_matching_category(): void
    {
        $category = ProductCategory::query()->where('name', 'Цемент')->firstOrFail();

        $product = Product::query()->create(['name' => 'Цемент Хуаксин M-500']);

        $this->assertTrue($product->productCategory->is($category));
    }

    public function test_creating_product_without_category_falls_back_to_other_category(): void
    {
        $category = ProductCategory::query()->where('name', 'Другое')->firstOrFail();

        $product = Product::query()->create(['name' => 'ОСБ 1.2']);

        $this->assertTrue($product->productCategory->is($category));
    }

    public function test_creating_product_with_category_keeps_explicit_category(): void
    {
        $explicitCategory = ProductCategory::factory()->create(['name' => 'Custom']);

        $product = Product::query()->create([
            'name' => 'Цемент Хуаксин M-500',
            'product_category_id' => $explicitCategory->id,
        ]);

        $this->assertTrue($product->productCategory->is($explicitCategory));
    }
}
