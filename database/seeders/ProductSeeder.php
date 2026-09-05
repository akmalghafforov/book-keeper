<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Цемент', 'Гипсокартон', 'Шифер', 'Рассыпной', 'Другое'] as $name) {
            ProductCategory::query()->firstOrCreate(['name' => $name]);
        }

        $categories = ProductCategory::query()->pluck('id', 'name');
        $catalogue = [
            'Цемент' => ['Цемент Хуаксин M-500', 'Цемент Хуаксин ПЦ-400', 'Цемент Мохир M-400', 'Цемент Мохир M-500', 'Цемент Хатлон M-400', 'Цемент Хатлон M-500'],
            'Гипсокартон' => ['Гипсокартон'],
            'Шифер' => ['Шифер'],
            'Рассыпной' => ['Цемент Рассыпной Мохир', 'Цемент Рассыпной Хуаксин'],
        ];

        foreach ($catalogue as $category => $products) {
            foreach ($products as $name) {
                Product::query()->firstOrCreate(['name' => $name], ['product_category_id' => $categories[$category]]);
            }
        }
    }
}
