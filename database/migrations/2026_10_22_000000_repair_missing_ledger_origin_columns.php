<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['debt_ledgers', 'provider_ledgers'] as $table) {
            if (Schema::hasColumn($table, 'origin')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('origin')->default('manual')->index();
            });

            if ($table === 'provider_ledgers') {
                DB::table($table)->whereNotNull('distribution_id')->update(['origin' => 'distribution']);
            } else {
                DB::table($table)
                    ->whereIn('reference_id', DB::table('distributions')->select('id'))
                    ->whereIn('type', ['charge', 'credit_note'])
                    ->update(['origin' => 'distribution']);
            }
        }
    }

    public function down(): void
    {
        // Repair only: retain columns required by the original ledger-origin migration.
    }
};
