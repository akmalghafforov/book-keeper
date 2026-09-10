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
            Schema::table($table, fn (Blueprint $t) => $t->string('origin')->default('manual')->index());
        } DB::table('provider_ledgers')->whereNotNull('distribution_id')->update(['origin' => 'distribution']);
        DB::table('debt_ledgers')->whereIn('reference_id', DB::table('distributions')->select('id'))->whereIn('type', ['charge', 'credit_note'])->update(['origin' => 'distribution']);
    }

    public function down(): void
    {
        foreach (['debt_ledgers', 'provider_ledgers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('origin'));
        }
    }
};
