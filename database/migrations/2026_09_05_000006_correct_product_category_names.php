<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['Цемент', 'Рассыпной', 'Гипсокартон', 'Другое'] as $name) {
            DB::table('product_categories')->updateOrInsert(
                ['name' => $name],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach ([
            'Рассипной' => 'Рассыпной',
            'Гипсакартон' => 'Гипсокартон',
            'Шифер' => 'Другое',
        ] as $incorrectName => $correctName) {
            $incorrectId = DB::table('product_categories')->where('name', $incorrectName)->value('id');
            $correctId = DB::table('product_categories')->where('name', $correctName)->value('id');

            if (! $incorrectId || ! $correctId) {
                continue;
            }

            DB::table('products')
                ->where('product_category_id', $incorrectId)
                ->update(['product_category_id' => $correctId]);

            DB::table('product_categories')->where('id', $incorrectId)->delete();
        }
    }

    public function down(): void
    {
        $now = now();

        DB::table('product_categories')->updateOrInsert(
            ['name' => 'Шифер'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $otherId = DB::table('product_categories')->where('name', 'Другое')->value('id');
        $slateId = DB::table('product_categories')->where('name', 'Шифер')->value('id');

        if ($otherId && $slateId) {
            DB::table('products')
                ->where('product_category_id', $otherId)
                ->where('name', 'Шифер')
                ->update(['product_category_id' => $slateId]);
        }
    }
};
