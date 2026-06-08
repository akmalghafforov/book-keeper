<?php

namespace App\Services;

use App\Models\GeneratedReport;
use App\Models\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProviderDebtReportDataBuilder
{
    public function __construct(
        private readonly GeneratedReportLedgerBoundaryService $ledgerBoundaryService,
    ) {}

    public function build(GeneratedReport $report): Provider
    {
        $providerId = (int) ($report->parameters['provider_id'] ?? 0);
        [$hasUpperBoundary, $upperLedgerId] = $this->resolveUpperLedgerBoundary($report);
        $rangeStart = Carbon::parse($report->parameters['range_start_date'])->startOfDay();
        $rangeStartLedgerId = (int) ($report->parameters['range_start_provider_ledger_id'] ?? 0);
        $rangeEnd = ! empty($report->parameters['range_end_date'])
            ? Carbon::parse($report->parameters['range_end_date'])->endOfDay()
            : null;

        $provider = Provider::query()
            ->with(['providerLedgers' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                if ($hasUpperBoundary) {
                    $query->where('provider_ledgers.id', '<=', $upperLedgerId);
                }

                $query->with(['product', 'distribution'])
                    ->inOperationOrder();
            }])
            ->findOrFail($providerId);

        $allLedgers = $provider->providerLedgers->values();
        $ledgerPositions = $allLedgers->pluck('id')->flip();
        $rangeStartPosition = $rangeStartLedgerId > 0
            ? $ledgerPositions->get($rangeStartLedgerId)
            : null;
        $openingLedgers = $allLedgers
            ->filter(function ($ledger) use ($rangeStart, $rangeStartLedgerId, $rangeStartPosition, $ledgerPositions) {
                $ledgerDate = $this->ledgerReportDate($ledger);

                return $ledgerDate->lt($rangeStart)
                    || ($ledgerDate->eq($rangeStart) && $this->ledgerComesBeforeRangeStart($ledger, $rangeStartLedgerId, $rangeStartPosition, $ledgerPositions));
            })
            ->values();
        $rangeLedgers = $allLedgers
            ->filter(function ($ledger) use ($rangeStart, $rangeStartLedgerId, $rangeStartPosition, $ledgerPositions, $rangeEnd) {
                $ledgerDate = $this->ledgerReportDate($ledger);
                $startsWithinRange = $ledgerDate->gt($rangeStart)
                    || ($ledgerDate->eq($rangeStart) && ! $this->ledgerComesBeforeRangeStart($ledger, $rangeStartLedgerId, $rangeStartPosition, $ledgerPositions));

                return $startsWithinRange
                    && ($rangeEnd === null || $ledgerDate->lte($rangeEnd));
            })
            ->values();
        $laterLedgers = $rangeEnd === null
            ? $allLedgers->take(0)
            : $allLedgers
                ->filter(fn ($ledger) => $this->ledgerReportDate($ledger)->gt($rangeEnd))
                ->values();

        $openingBalance = $this->sumLedgerBalanceDeltas($openingLedgers);
        $selectedRangeTotal = $this->sumLedgerBalanceDeltas($rangeLedgers);
        $rangeClosingBalance = $openingBalance + $selectedRangeTotal;
        $laterTransactionsTotal = $this->sumLedgerBalanceDeltas($laterLedgers);

        $provider->is_date_range_report = true;
        $provider->range_start_date = $rangeStart;
        $provider->range_end_date = $rangeEnd;
        $provider->opening_balance_total = $openingBalance;
        $provider->opening_balance_transactions_count = $openingLedgers->count();
        $provider->has_opening_balance_transactions = $openingLedgers->isNotEmpty();
        $provider->selected_range_total = $selectedRangeTotal;
        $provider->range_closing_balance = $rangeClosingBalance;
        $provider->later_transactions_total = $laterTransactionsTotal;
        $provider->later_transactions_count = $laterLedgers->count();
        $provider->has_later_transactions = $laterLedgers->isNotEmpty();
        $provider->calculated_total_debt = $this->sumLedgerBalanceDeltas($allLedgers);

        $runningBalance = $openingBalance;
        $provider->recentLedgers = $rangeLedgers
            ->map(function ($ledger) use (&$runningBalance) {
                $runningBalance += $this->ledgerBalanceDelta($ledger);
                $ledger->running_balance = $runningBalance;

                return $ledger;
            });

        $provider->last_included_ledger_id = $hasUpperBoundary
            ? $upperLedgerId
            : ($allLedgers->last()?->id ?? 0);

        return $provider;
    }

    private function resolveUpperLedgerBoundary(GeneratedReport $report): array
    {
        if (! $this->ledgerBoundaryService->supportsLedgerBoundary($report)) {
            return [false, 0];
        }

        return [true, $this->ledgerBoundaryService->resolveReportLastIncludedLedgerId($report)];
    }

    private function ledgerReportDate($ledger): Carbon
    {
        return Carbon::parse($ledger->provider_received_at ?? $ledger->transaction_date ?? $ledger->created_at)->startOfDay();
    }

    private function ledgerComesBeforeRangeStart($ledger, int $rangeStartLedgerId, ?int $rangeStartPosition, Collection $ledgerPositions): bool
    {
        if ($rangeStartLedgerId <= 0) {
            return false;
        }

        $ledgerPosition = $ledgerPositions->get($ledger->id);

        if ($rangeStartPosition === null || $ledgerPosition === null) {
            return $ledger->id < $rangeStartLedgerId;
        }

        return $ledgerPosition < $rangeStartPosition;
    }

    private function ledgerBalanceDelta($ledger): float
    {
        if ($ledger->type === 'charge') {
            return (float) $ledger->amount;
        }

        return -(float) $ledger->amount;
    }

    private function sumLedgerBalanceDeltas(Collection $ledgers): float
    {
        return $ledgers->reduce(function ($carry, $ledger) {
            return $carry + $this->ledgerBalanceDelta($ledger);
        }, 0.0);
    }
}
