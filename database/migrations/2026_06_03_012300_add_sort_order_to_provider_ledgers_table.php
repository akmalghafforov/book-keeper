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
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->after('transaction_date');
            $table->index(['provider_id', 'transaction_date', 'sort_order'], 'provider_ledgers_group_order_idx');
        });

        $positions = [];

        DB::table('provider_ledgers')
            ->select(['id', 'provider_id', 'transaction_date'])
            ->orderBy('provider_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->chunk(500, function ($ledgers) use (&$positions): void {
                foreach ($ledgers as $ledger) {
                    $key = $ledger->provider_id.'|'.$ledger->transaction_date;
                    $positions[$key] = ($positions[$key] ?? 0) + 1;

                    DB::table('provider_ledgers')
                        ->where('id', $ledger->id)
                        ->update(['sort_order' => $positions[$key]]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropIndex('provider_ledgers_group_order_idx');
            $table->dropColumn('sort_order');
        });
    }
};
