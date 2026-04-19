<?php

use App\Services\GeneratedReportLedgerBoundaryService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(GeneratedReportLedgerBoundaryService::class)->backfillMissingLastIncludedLedgerIds();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
