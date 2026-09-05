<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Цемент', 'Гипсокартон', 'Шифер', 'Рассыпной', 'Другое'] as $name) {
            ProductCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
