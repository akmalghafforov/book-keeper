<?php

namespace App\Services;

use App\Models\DebtLedger;
use App\Models\GeneratedReport;

class GeneratedReportLedgerBoundaryService
{
    public function resolveReportLastIncludedLedgerId(GeneratedReport $report): int
    {
        if ($report->last_included_ledger_id !== null) {
            return (int) $report->last_included_ledger_id;
        }

        if ($report->type !== 'single_client_debt' || $report->status !== 'completed') {
            return 0;
        }

        $clientId = (int) ($report->parameters['client_id'] ?? 0);
        $cutoff = $report->updated_at ?? $report->created_at;

        if ($clientId <= 0 || $cutoff === null) {
            return 0;
        }

        return (int) (DebtLedger::query()
            ->where('client_id', $clientId)
            ->where('created_at', '<=', $cutoff)
            ->max('id') ?? 0);
    }

    public function backfillMissingLastIncludedLedgerIds(): int
    {
        $updatedCount = 0;

        GeneratedReport::query()
            ->where('type', 'single_client_debt')
            ->where('status', 'completed')
            ->whereNull('last_included_ledger_id')
            ->orderBy('id')
            ->chunkById(100, function ($reports) use (&$updatedCount): void {
                foreach ($reports as $report) {
                    $report->forceFill([
                        'last_included_ledger_id' => $this->resolveReportLastIncludedLedgerId($report),
                    ])->saveQuietly();

                    $updatedCount++;
                }
            });

        return $updatedCount;
    }
}
