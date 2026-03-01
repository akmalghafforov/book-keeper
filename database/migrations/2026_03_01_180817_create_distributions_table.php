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
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->enum('quantity_unit', ['per_ton', 'per_bag', 'per_piece']);
            $table->decimal('quantity', 15, 3);
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->date('distribution_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};
