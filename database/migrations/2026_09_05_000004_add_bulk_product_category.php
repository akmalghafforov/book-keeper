<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('product_categories')->updateOrInsert(
            ['name' => 'Рассыпной'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $categoryId = DB::table('product_categories')->where('name', 'Рассыпной')->value('id');

        DB::table('products')
            ->whereRaw('LOWER(name) LIKE ?', ['%рассыпной%'])
            ->update(['product_category_id' => $categoryId]);
    }

    public function down(): void
    {
        $otherId = DB::table('product_categories')->where('name', 'Другое')->value('id');

        if ($otherId) {
            DB::table('products')->where('product_category_id', DB::table('product_categories')->where('name', 'Рассыпной')->value('id'))
                ->update(['product_category_id' => $otherId]);
        }

        DB::table('product_categories')->where('name', 'Рассыпной')->delete();
    }
};
