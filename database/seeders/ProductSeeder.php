<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::query()->create(['name' => "Цемент Хуаксин M-500"]);
        Product::query()->create(['name' => "Цемент Хуаксин ПЦ-400"]);
        Product::query()->create(['name' => "Цемент Мохир M-400"]);
        Product::query()->create(['name' => "Цемент Мохир M-500"]);
        Product::query()->create(['name' => "Цемент Хатлон M-400"]);
        Product::query()->create(['name' => "Цемент Хатлон M-500"]);
        Product::query()->create(['name' => "Гипсокартон"]);
        Product::query()->create(['name' => "Шифер"]);
        Product::query()->create(['name' => "Цемент Рассыпной Мохир"]);
        Product::query()->create(['name' => "Цемент Рассыпной Хуаксин"]);
    }
}
