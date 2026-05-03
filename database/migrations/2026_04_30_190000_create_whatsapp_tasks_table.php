<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_tasks', function (Blueprint $table) {
            $table->id();
            $table->enum('task_type', [
                'goods_debt_pieces',
                'goods_debt_tons',
                'client_debt_transfer',
                'client_payment',
            ]);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('task_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_type', 'status']);
            $table->index('task_date');
        });

        Schema::create('whatsapp_message_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['whatsapp_task_id', 'whatsapp_message_id'], 'whatsapp_task_message_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_task');
        Schema::dropIfExists('whatsapp_tasks');
    }
};
