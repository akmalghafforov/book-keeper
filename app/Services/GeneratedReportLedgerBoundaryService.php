<?php

namespace App\Services;

use App\Models\DebtLedger;
use App\Models\GeneratedReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

class GeneratedReportLedgerBoundaryService
{
    private const BOUNDARY_REPORT_TYPES = [
        'client_debt',
        'single_client_debt',
    ];

    public function resolveReportLastIncludedLedgerId(GeneratedReport $report): int
    {
        if ($report->last_included_ledger_id !== null) {
            return (int) $report->last_included_ledger_id;
        }

        if (! $this->supportsLedgerBoundary($report)) {
            return 0;
        }

        return $this->snapshotLastIncludedLedgerId(
            $report->type,
            $report->parameters ?? [],
            $this->resolveReportCutoffAt($report),
        );
    }

    public function snapshotLastIncludedLedgerId(string $type, array $parameters = [], ?CarbonInterface $cutoff = null): int
    {
        if (! in_array($type, self::BOUNDARY_REPORT_TYPES, true)) {
            return 0;
        }

        $query = DebtLedger::query();

        if ($type === 'single_client_debt') {
            $clientId = (int) ($parameters['client_id'] ?? 0);

            if ($clientId <= 0) {
                return 0;
            }

            $query->where('client_id', $clientId);
        }

        if ($cutoff !== null) {
            $query->where('created_at', '<=', $cutoff);
        }

        return (int) ($query->max('id') ?? 0);
    }

    public function resolveReportCutoffAt(GeneratedReport $report): ?CarbonInterface
    {
        $cutoff = ($report->parameters ?? [])['cutoff_at'] ?? null;

        if ($cutoff) {
            try {
                return Carbon::parse($cutoff);
            } catch (Throwable) {
                // Fall back to the timestamps below for malformed legacy parameters.
            }
        }

        if ($report->status === 'completed') {
            return $report->updated_at ?? $report->created_at;
        }

        return $report->created_at ?? $report->updated_at;
    }

    public function supportsLedgerBoundary(GeneratedReport $report): bool
    {
        return in_array($report->type, self::BOUNDARY_REPORT_TYPES, true);
    }

    public function backfillMissingLastIncludedLedgerIds(): int
    {
        $updatedCount = 0;

        GeneratedReport::query()
            ->whereIn('type', self::BOUNDARY_REPORT_TYPES)
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
