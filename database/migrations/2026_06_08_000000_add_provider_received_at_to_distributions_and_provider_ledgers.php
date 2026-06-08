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
        Schema::table('distributions', function (Blueprint $table) {
            $table->dateTime('provider_received_at')
                ->nullable()
                ->after('distribution_date');
        });

        DB::table('distributions')
            ->whereNull('provider_received_at')
            ->update(['provider_received_at' => DB::raw('distribution_date')]);

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dateTime('provider_received_at')
                ->nullable()
                ->after('transaction_date');
            $table->index(['provider_id', 'provider_received_at'], 'provider_ledgers_provider_received_at_idx');
        });

        DB::table('provider_ledgers')
            ->leftJoin('distributions', 'provider_ledgers.distribution_id', '=', 'distributions.id')
            ->select([
                'provider_ledgers.id',
                'provider_ledgers.transaction_date',
                'distributions.provider_received_at as distribution_provider_received_at',
            ])
            ->orderBy('provider_ledgers.id')
            ->chunk(500, function ($ledgers): void {
                foreach ($ledgers as $ledger) {
                    DB::table('provider_ledgers')
                        ->where('id', $ledger->id)
                        ->update([
                            'provider_received_at' => $ledger->distribution_provider_received_at
                                ?? $ledger->transaction_date,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropIndex('provider_ledgers_provider_received_at_idx');
            $table->dropColumn('provider_received_at');
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->dropColumn('provider_received_at');
        });
    }
};
