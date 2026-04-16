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
        Schema::table('debt_ledgers', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('amount');
        });

        DB::table('debt_ledgers')->update([
            'transaction_date' => DB::raw('date(created_at)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debt_ledgers', function (Blueprint $table) {
            $table->dropColumn('transaction_date');
        });
    }
};
