<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::rename('whatsapp_tasks', 'whatsapp_tasks_old');
        DB::statement('DROP INDEX IF EXISTS whatsapp_tasks_task_type_status_index');
        DB::statement('DROP INDEX IF EXISTS whatsapp_tasks_task_date_index');

        $this->createWhatsappTasksTable([
            'goods_debt_pieces',
            'goods_debt_tons',
            'client_debt_transfer',
            'client_payment',
        ]);

        DB::statement("
            INSERT INTO whatsapp_tasks (
                id,
                task_type,
                status,
                client_id,
                credit_client_id,
                amount,
                task_date,
                notes,
                created_by,
                created_at,
                updated_at
            )
            SELECT
                id,
                CASE task_type
                    WHEN 'debt' THEN 'goods_debt_pieces'
                    WHEN 'debt_with_credits' THEN 'client_debt_transfer'
                    WHEN 'payment' THEN 'client_payment'
                    ELSE task_type
                END,
                status,
                client_id,
                credit_client_id,
                amount,
                task_date,
                notes,
                created_by,
                created_at,
                updated_at
            FROM whatsapp_tasks_old
        ");

        Schema::drop('whatsapp_tasks_old');
        $this->rebuildPivotTable('whatsapp_message_task_old');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::rename('whatsapp_tasks', 'whatsapp_tasks_new');
        DB::statement('DROP INDEX IF EXISTS whatsapp_tasks_task_type_status_index');
        DB::statement('DROP INDEX IF EXISTS whatsapp_tasks_task_date_index');

        $this->createWhatsappTasksTable([
            'debt',
            'debt_with_credits',
            'payment',
        ]);

        DB::statement("
            INSERT INTO whatsapp_tasks (
                id,
                task_type,
                status,
                client_id,
                credit_client_id,
                amount,
                task_date,
                notes,
                created_by,
                created_at,
                updated_at
            )
            SELECT
                id,
                CASE task_type
                    WHEN 'goods_debt_pieces' THEN 'debt'
                    WHEN 'goods_debt_tons' THEN 'debt'
                    WHEN 'client_debt_transfer' THEN 'debt_with_credits'
                    WHEN 'client_payment' THEN 'payment'
                    ELSE task_type
                END,
                status,
                client_id,
                credit_client_id,
                amount,
                task_date,
                notes,
                created_by,
                created_at,
                updated_at
            FROM whatsapp_tasks_new
        ");

        Schema::drop('whatsapp_tasks_new');
        $this->rebuildPivotTable('whatsapp_message_task_new');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    /**
     * @param  array<int, string>  $taskTypes
     */
    private function createWhatsappTasksTable(array $taskTypes): void
    {
        Schema::create('whatsapp_tasks', function (Blueprint $table) use ($taskTypes) {
            $table->id();
            $table->enum('task_type', $taskTypes);
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
    }

    private function rebuildPivotTable(string $temporaryTableName): void
    {
        if (! Schema::hasTable('whatsapp_message_task')) {
            return;
        }

        Schema::rename('whatsapp_message_task', $temporaryTableName);
        DB::statement('DROP INDEX IF EXISTS whatsapp_task_message_unique');

        Schema::create('whatsapp_message_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['whatsapp_task_id', 'whatsapp_message_id'], 'whatsapp_task_message_unique');
        });

        DB::statement("
            INSERT INTO whatsapp_message_task (
                id,
                whatsapp_task_id,
                whatsapp_message_id,
                created_at,
                updated_at
            )
            SELECT
                id,
                whatsapp_task_id,
                whatsapp_message_id,
                created_at,
                updated_at
            FROM {$temporaryTableName}
        ");

        Schema::drop($temporaryTableName);
    }
};
