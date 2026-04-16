<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('supplies', 'suppliers');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('delivery_date');
            $table->string('car_number', 50)->nullable(false)->change();
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->renameColumn('supply_id', 'supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->renameColumn('supplier_id', 'supply_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->date('delivery_date')->nullable(); // nullable as a placeholder, original was date
            $table->string('car_number', 50)->nullable()->change();
        });

        Schema::rename('suppliers', 'supplies');
    }
};
