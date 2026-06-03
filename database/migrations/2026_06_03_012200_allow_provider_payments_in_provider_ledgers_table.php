<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->string('type', 20)
                ->default('charge')
                ->after('provider_id');
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropForeign(['distribution_id']);
            $table->dropUnique(['distribution_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('distribution_id')->nullable()->change();
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->decimal('quantity', 15, 3)->nullable()->change();
            $table->decimal('buy_price', 15, 4)->nullable()->change();
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->unique('distribution_id');
            $table->foreign('distribution_id')->references('id')->on('distributions')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropForeign(['distribution_id']);
            $table->dropUnique(['distribution_id']);
            $table->dropForeign(['product_id']);
        });

        DB::table('provider_ledgers')->where('type', 'payment')->delete();

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('distribution_id')->nullable(false)->change();
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->decimal('quantity', 15, 3)->nullable(false)->change();
            $table->decimal('buy_price', 15, 4)->nullable(false)->change();
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->unique('distribution_id');
            $table->foreign('distribution_id')->references('id')->on('distributions')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->dropColumn('type');
        });
    }
};
