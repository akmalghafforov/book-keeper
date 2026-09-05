<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('usage_priority')->default(0)->after('product_category_id');
            $table->index(['product_category_id', 'usage_priority', 'name', 'id'], 'products_category_usage_name_id_index');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedInteger('usage_priority')->default(0)->after('name');
            $table->index(['usage_priority', 'name', 'id'], 'product_categories_usage_name_id_index');
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->index(['deleted_at', 'distribution_date', 'product_id'], 'distributions_usage_window_index');
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropIndex('distributions_usage_window_index');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex('product_categories_usage_name_id_index');
            $table->dropColumn('usage_priority');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_usage_name_id_index');
            $table->dropColumn('usage_priority');
        });
    }
};
