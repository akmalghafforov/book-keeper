<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();
        foreach (['Цемент', 'Гипсокартон', 'Шифер', 'Другое'] as $name) {
            DB::table('product_categories')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_category_id')->nullable()->constrained()->after('id');
        });

        $categories = DB::table('product_categories')->pluck('id', 'name');
        DB::table('products')->orderBy('id')->eachById(function ($product) use ($categories) {
            $name = mb_strtolower($product->name);
            $category = str_contains($name, 'цемент') ? 'Цемент'
                : (str_contains($name, 'гипсокартон') ? 'Гипсокартон'
                : (str_contains($name, 'шифер') ? 'Шифер' : 'Другое'));

            DB::table('products')->where('id', $product->id)->update(['product_category_id' => $categories[$category]]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_category_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_category_id');
        });
        Schema::dropIfExists('product_categories');
    }
};
