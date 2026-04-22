<?php

namespace App\Services;

use App\Models\Client;
use App\Models\GeneratedReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientDebtReportDataBuilder
{
    public function __construct(
        private readonly GeneratedReportLedgerBoundaryService $ledgerBoundaryService,
    ) {}

    public function build(GeneratedReport $report): Client
    {
        if ($this->isDateRangeReport($report)) {
            return $this->buildDateRange($report);
        }

        $clientId = (int) ($report->parameters['client_id'] ?? 0);
        [$hasUpperBoundary, $upperLedgerId] = $this->resolveUpperLedgerBoundary($report);

        $ledgerBalanceDelta = static function ($ledger): float {
            if ($ledger->type === 'charge') {
                return (float) $ledger->amount;
            }

            return -(float) $ledger->amount;
        };

        $client = Client::query()
            ->with(['debtLedgers' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);

                $query->with(['distribution.product', 'distribution.supplier', 'distribution.shop', 'distribution.client'])
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('id', 'asc');
            }])
            ->withSum(['debtLedgers as total_charges' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $query->where('type', 'charge');
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);
            }], 'amount')
            ->withSum(['debtLedgers as total_payments' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $query->where('type', 'payment');
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);
            }], 'amount')
            ->withSum(['debtLedgers as total_credit_notes' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $query->where('type', 'credit_note');
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);
            }], 'amount')
            ->findOrFail($clientId);

        $client->calculated_total_debt = ($client->total_charges ?? 0) - ($client->total_payments ?? 0) - ($client->total_credit_notes ?? 0);

        $previousReportsQuery = GeneratedReport::query()
            ->where('type', 'single_client_debt')
            ->where('status', 'completed')
            ->where('parameters->client_id', $clientId)
            ->where('serial_number', '<', ($report->serial_number ?? $report->id));

        $previousReport = (clone $previousReportsQuery)
            ->latest('serial_number')
            ->first();

        $reportedThroughLedgerId = $previousReport
            ? $this->ledgerBoundaryService->resolveReportLastIncludedLedgerId($previousReport)
            : 0;
        $allLedgers = $client->debtLedgers->values();
        $previouslyReportedLedgers = $reportedThroughLedgerId > 0
            ? $allLedgers->filter(fn ($ledger) => $ledger->id <= $reportedThroughLedgerId)->values()
            : $allLedgers->take(0);

        $client->previous_report_count = (clone $previousReportsQuery)->count();
        $client->has_previously_reported_transactions = $previouslyReportedLedgers->isNotEmpty();
        $client->previously_reported_total = $previouslyReportedLedgers->reduce(function ($carry, $ledger) use ($ledgerBalanceDelta) {
            return $carry + $ledgerBalanceDelta($ledger);
        }, 0.0);
        $client->reported_through_ledger_id = $reportedThroughLedgerId;

        $runningBalance = (float) $client->previously_reported_total;
        $client->recentLedgers = $allLedgers
            ->reject(fn ($ledger) => $reportedThroughLedgerId > 0 && $ledger->id <= $reportedThroughLedgerId)
            ->values()
            ->map(function ($ledger) use (&$runningBalance, $ledgerBalanceDelta) {
                $runningBalance += $ledgerBalanceDelta($ledger);
                $ledger->running_balance = $runningBalance;

                return $ledger;
            });

        $client->last_included_ledger_id = $hasUpperBoundary
            ? $upperLedgerId
            : ($client->recentLedgers->last()?->id ?? $reportedThroughLedgerId);

        return $client;
    }

    private function buildDateRange(GeneratedReport $report): Client
    {
        $clientId = (int) ($report->parameters['client_id'] ?? 0);
        [$hasUpperBoundary, $upperLedgerId] = $this->resolveUpperLedgerBoundary($report);
        $rangeStart = Carbon::parse($report->parameters['range_start_date'])->startOfDay();
        $rangeEnd = ! empty($report->parameters['range_end_date'])
            ? Carbon::parse($report->parameters['range_end_date'])->endOfDay()
            : null;

        $client = Client::query()
            ->with(['debtLedgers' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);

                $query->with(['distribution.product', 'distribution.supplier', 'distribution.shop', 'distribution.client'])
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('id', 'asc');
            }])
            ->findOrFail($clientId);

        $allLedgers = $client->debtLedgers->values();
        $openingLedgers = $allLedgers
            ->filter(fn ($ledger) => $this->ledgerReportDate($ledger)->lt($rangeStart))
            ->values();
        $rangeLedgers = $allLedgers
            ->filter(function ($ledger) use ($rangeStart, $rangeEnd) {
                $ledgerDate = $this->ledgerReportDate($ledger);

                return $ledgerDate->gte($rangeStart)
                    && ($rangeEnd === null || $ledgerDate->lte($rangeEnd));
            })
            ->values();
        $laterLedgers = $rangeEnd === null
            ? $allLedgers->take(0)
            : $allLedgers
                ->filter(fn ($ledger) => $this->ledgerReportDate($ledger)->gt($rangeEnd))
                ->values();

        $openingBalance = $this->sumLedgerBalanceDeltas($openingLedgers);
        $laterTransactionsTotal = $this->sumLedgerBalanceDeltas($laterLedgers);

        $client->is_date_range_report = true;
        $client->range_start_date = $rangeStart;
        $client->range_end_date = $rangeEnd;
        $client->opening_balance_total = $openingBalance;
        $client->opening_balance_transactions_count = $openingLedgers->count();
        $client->has_opening_balance_transactions = $openingLedgers->isNotEmpty();
        $client->later_transactions_total = $laterTransactionsTotal;
        $client->later_transactions_count = $laterLedgers->count();
        $client->has_later_transactions = $laterLedgers->isNotEmpty();
        $client->calculated_total_debt = $this->sumLedgerBalanceDeltas($allLedgers);

        $runningBalance = $openingBalance;
        $client->recentLedgers = $rangeLedgers
            ->map(function ($ledger) use (&$runningBalance) {
                $runningBalance += $this->ledgerBalanceDelta($ledger);
                $ledger->running_balance = $runningBalance;

                return $ledger;
            });

        $client->last_included_ledger_id = $hasUpperBoundary
            ? $upperLedgerId
            : ($allLedgers->last()?->id ?? 0);

        return $client;
    }

    public function buildAll(GeneratedReport $report): Collection
    {
        [$hasUpperBoundary, $upperLedgerId] = $this->resolveUpperLedgerBoundary($report);

        return Client::query()
            ->withSum(['debtLedgers as total_charges' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $query->where('type', 'charge');
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);
            }], 'amount')
            ->withSum(['debtLedgers as total_payments' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $query->where('type', 'payment');
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);
            }], 'amount')
            ->withSum(['debtLedgers as total_credit_notes' => function ($query) use ($hasUpperBoundary, $upperLedgerId) {
                $query->where('type', 'credit_note');
                $this->applyUpperLedgerBoundary($query, $hasUpperBoundary, $upperLedgerId);
            }], 'amount')
            ->get()
            ->map(function ($client) {
                $client->calculated_total_debt = ($client->total_charges ?? 0) - ($client->total_payments ?? 0) - ($client->total_credit_notes ?? 0);

                return $client;
            })
            ->filter(fn ($client) => $client->calculated_total_debt != 0)
            ->sortBy('name')
            ->values();
    }

    private function resolveUpperLedgerBoundary(GeneratedReport $report): array
    {
        if (! $this->ledgerBoundaryService->supportsLedgerBoundary($report)) {
            return [false, 0];
        }

        return [true, $this->ledgerBoundaryService->resolveReportLastIncludedLedgerId($report)];
    }

    private function applyUpperLedgerBoundary($query, bool $hasUpperBoundary, int $upperLedgerId): void
    {
        if (! $hasUpperBoundary) {
            return;
        }

        $query->where('debt_ledgers.id', '<=', $upperLedgerId);
    }

    private function isDateRangeReport(GeneratedReport $report): bool
    {
        $parameters = $report->parameters ?? [];

        return $report->type === 'single_client_debt_range'
            && ! empty($parameters['range_start_date']);
    }

    private function ledgerReportDate($ledger): Carbon
    {
        return Carbon::parse(
            $ledger->transaction_date
                ?? $ledger->distribution?->distribution_date
                ?? $ledger->created_at
        )->startOfDay();
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
