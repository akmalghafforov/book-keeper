<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\GeneratedReport;
use App\Services\GeneratedReportLedgerBoundaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class OperationController extends Controller
{
    private const CLIENT_DEBT_REPORT_TYPES = [
        'single_client_debt',
        'single_client_debt_range',
    ];

    public function __construct(
        private readonly GeneratedReportLedgerBoundaryService $ledgerBoundaryService,
    ) {}

    public function index(Request $request)
    {
        $query = DebtLedger::with(['client', 'distribution.product', 'distribution.supplier'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('car_number')) {
            $carNumber = $request->car_number;
            $query->whereHas('distribution.supplier', function ($q) use ($carNumber) {
                $q->where('car_number', 'like', '%'.$carNumber.'%');
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', '%'.$search.'%')
                    ->orWhere('reference_id', 'like', '%'.$search.'%')
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('distribution.product', function ($pq) use ($search) {
                        $pq->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('distribution.supplier', function ($sq) use ($search) {
                        $sq->where('car_number', 'like', '%'.$search.'%');
                    });
            });
        }

        $operations = $query->paginate(100)->withQueryString();
        $this->hydrateOperationBalances($operations);
        $this->hydrateRecentDebtReportMarkers($operations, $request);

        $clients = Client::orderBy('name')->get();

        return view('admin.operations.index', compact('operations', 'clients'));
    }

    private function hydrateOperationBalances($operations): void
    {
        $displayedOperations = $operations->getCollection();

        if ($displayedOperations->isEmpty()) {
            return;
        }

        $displayedOperationIds = $displayedOperations->pluck('id')->all();
        $displayedOperationIdLookup = array_flip($displayedOperationIds);
        $balancesByClient = [];

        DebtLedger::query()
            ->whereIn('client_id', $displayedOperations->pluck('client_id')->unique()->all())
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'client_id', 'type', 'amount'])
            ->each(function (DebtLedger $ledger) use (&$balancesByClient, $displayedOperationIdLookup, $displayedOperations): void {
                $balancesByClient[$ledger->client_id] ??= 0.0;
                $balancesByClient[$ledger->client_id] += $ledger->type === 'charge'
                    ? (float) $ledger->amount
                    : -(float) $ledger->amount;

                if (! isset($displayedOperationIdLookup[$ledger->id])) {
                    return;
                }

                $displayedOperations
                    ->firstWhere('id', $ledger->id)
                    ?->setAttribute('balance_after_operation', $balancesByClient[$ledger->client_id]);
            });
    }

    private function hydrateRecentDebtReportMarkers($operations, Request $request): void
    {
        $displayedOperations = $operations->getCollection();

        $displayedOperations->each(
            fn (DebtLedger $operation) => $operation->setAttribute('included_in_recent_debt_report', false)
        );

        if ($displayedOperations->isEmpty() || ! $request->filled('client_id')) {
            return;
        }

        $clientId = (int) $request->query('client_id');

        if ($clientId <= 0) {
            return;
        }

        $report = $this->latestCompletedClientDebtReport($clientId);

        if (! $report) {
            return;
        }

        $lastIncludedLedgerId = $this->ledgerBoundaryService->resolveReportLastIncludedLedgerId($report);

        if ($lastIncludedLedgerId <= 0) {
            return;
        }

        $includedOperationIdLookup = array_flip(
            $this->recentDebtReportLedgerIds($displayedOperations, $report, $lastIncludedLedgerId)
        );

        $displayedOperations->each(function (DebtLedger $operation) use ($includedOperationIdLookup): void {
            if (! isset($includedOperationIdLookup[$operation->id])) {
                return;
            }

            $operation->setAttribute('included_in_recent_debt_report', true);
        });
    }

    private function latestCompletedClientDebtReport(int $clientId): ?GeneratedReport
    {
        return GeneratedReport::query()
            ->whereIn('type', self::CLIENT_DEBT_REPORT_TYPES)
            ->where('status', 'completed')
            ->where('parameters->client_id', $clientId)
            ->orderByDesc('serial_number')
            ->orderByDesc('id')
            ->first();
    }

    private function recentDebtReportLedgerIds(Collection $displayedOperations, GeneratedReport $report, int $lastIncludedLedgerId): array
    {
        if ($report->type === 'single_client_debt') {
            $previousBoundary = $this->previousSingleClientDebtReportBoundary($report);

            return $displayedOperations
                ->filter(fn (DebtLedger $operation) => $operation->id > $previousBoundary && $operation->id <= $lastIncludedLedgerId)
                ->pluck('id')
                ->all();
        }

        if ($report->type !== 'single_client_debt_range') {
            return [];
        }

        return $displayedOperations
            ->filter(fn (DebtLedger $operation) => $this->operationIsIncludedInRangeReport($operation, $report, $lastIncludedLedgerId))
            ->pluck('id')
            ->all();
    }

    private function previousSingleClientDebtReportBoundary(GeneratedReport $report): int
    {
        $clientId = (int) (($report->parameters ?? [])['client_id'] ?? 0);

        if ($clientId <= 0) {
            return 0;
        }

        $previousReport = GeneratedReport::query()
            ->where('type', 'single_client_debt')
            ->where('status', 'completed')
            ->where('parameters->client_id', $clientId)
            ->where('serial_number', '<', ($report->serial_number ?? $report->id))
            ->orderByDesc('serial_number')
            ->first();

        return $previousReport
            ? $this->ledgerBoundaryService->resolveReportLastIncludedLedgerId($previousReport)
            : 0;
    }

    private function operationIsIncludedInRangeReport(DebtLedger $operation, GeneratedReport $report, int $lastIncludedLedgerId): bool
    {
        if ($operation->id > $lastIncludedLedgerId) {
            return false;
        }

        $parameters = $report->parameters ?? [];

        if (empty($parameters['range_start_date'])) {
            return false;
        }

        try {
            $rangeStart = Carbon::parse($parameters['range_start_date'])->startOfDay();
            $rangeEnd = ! empty($parameters['range_end_date'])
                ? Carbon::parse($parameters['range_end_date'])->endOfDay()
                : null;
            $operationDate = $this->operationReportDate($operation);
        } catch (Throwable) {
            return false;
        }

        $rangeStartLedgerId = (int) ($parameters['range_start_ledger_id'] ?? 0);
        $startsWithinRange = $operationDate->gt($rangeStart)
            || ($operationDate->eq($rangeStart) && ($rangeStartLedgerId <= 0 || $operation->id >= $rangeStartLedgerId));

        return $startsWithinRange
            && ($rangeEnd === null || $operationDate->lte($rangeEnd));
    }

    private function operationReportDate(DebtLedger $operation): Carbon
    {
        return Carbon::parse(
            $operation->transaction_date
                ?? $operation->distribution?->distribution_date
                ?? $operation->created_at
        )->startOfDay();
    }
}
