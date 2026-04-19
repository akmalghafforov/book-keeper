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
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('serial_number')->nullable()->after('id');
            $table->unsignedBigInteger('last_included_ledger_id')->nullable()->after('parameters');
            $table->unique('serial_number');
        });

        DB::table('generated_reports')->update([
            'serial_number' => DB::raw('id'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropColumn(['serial_number', 'last_included_ledger_id']);
        });
    }
};
