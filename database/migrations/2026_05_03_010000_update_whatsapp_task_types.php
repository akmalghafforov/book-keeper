<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE whatsapp_tasks MODIFY task_type ENUM('debt', 'debt_with_credits', 'payment', 'goods_debt_pieces', 'goods_debt_tons', 'client_debt_transfer', 'client_payment') NOT NULL");
        }

        DB::table('whatsapp_tasks')
            ->where('task_type', 'debt')
            ->update(['task_type' => 'goods_debt_pieces']);

        DB::table('whatsapp_tasks')
            ->where('task_type', 'debt_with_credits')
            ->update(['task_type' => 'client_debt_transfer']);

        DB::table('whatsapp_tasks')
            ->where('task_type', 'payment')
            ->update(['task_type' => 'client_payment']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE whatsapp_tasks MODIFY task_type ENUM('goods_debt_pieces', 'goods_debt_tons', 'client_debt_transfer', 'client_payment') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE whatsapp_tasks MODIFY task_type ENUM('debt', 'debt_with_credits', 'payment', 'goods_debt_pieces', 'goods_debt_tons', 'client_debt_transfer', 'client_payment') NOT NULL");
        }

        DB::table('whatsapp_tasks')
            ->where('task_type', 'goods_debt_pieces')
            ->update(['task_type' => 'debt']);

        DB::table('whatsapp_tasks')
            ->where('task_type', 'goods_debt_tons')
            ->update(['task_type' => 'debt']);

        DB::table('whatsapp_tasks')
            ->where('task_type', 'client_debt_transfer')
            ->update(['task_type' => 'debt_with_credits']);

        DB::table('whatsapp_tasks')
            ->where('task_type', 'client_payment')
            ->update(['task_type' => 'payment']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE whatsapp_tasks MODIFY task_type ENUM('debt', 'debt_with_credits', 'payment') NOT NULL");
        }
    }
};
