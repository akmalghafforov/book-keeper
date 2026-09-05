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
        foreach (['Цемент', 'Рассыпной', 'Гипсокартон', 'Другое'] as $name) {
            ProductCategory::query()->firstOrCreate(['name' => $name]);
        }

        $categories = ProductCategory::query()->pluck('id', 'name');
        $catalogue = [
            'Цемент' => [
                'Ёқут М500',
                'Ёқут M400',
                'Тоҷ Смент',
                'Яксин М500',
                'Яксин М400',
                'Уқоб M500',
                'Уқоб M400',
                'Хуаксин M500',
                'Хуаксин M400',
                'Мохир M400',
                'Мохир M500',
                'Хатлон M400',
                'Хатлон M500',
            ],
            'Рассыпной' => [
                'Тоҷиксемент Рассыпной М500',
                'Рассипной Ганҷ M500',
                'Рассипной Ганҷ M400',
                'Портланд Рассипной',
                'Рассыпной Мохир M500',
                'Рассыпной Хуаксин M500',
            ],
            'Гипсокартон' => [
                'Гипсокартон',
                'Гипсакартон Кабуд',
                'Гипс. стеновой',
                'Гипс потолочный - простой',
                'Гипс потолочный - влагастойкий',
            ],
            'Другое' => ['Шифер', 'ОСБ 0.9', 'Оҳок', 'Арматур', 'ОСБ 0.6'],
        ];

        foreach ($catalogue as $category => $products) {
            foreach ($products as $name) {
                Product::query()->updateOrCreate(
                    ['name' => $name],
                    ['product_category_id' => $categories[$category]],
                );
            }
        }
    }
}
