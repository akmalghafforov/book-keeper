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
        Schema::create('debt_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->enum('type', ['charge', 'payment', 'credit_note']);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_ledgers');
    }
};
