<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateClientDebtReport;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Services\GeneratedReportLedgerBoundaryService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly GeneratedReportLedgerBoundaryService $ledgerBoundaryService,
    ) {}

    public function index()
    {
        $reports = GeneratedReport::latest()->paginate(10);
        return view('admin.reports.index', compact('reports'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:png,jpg',
        ]);

        $cutoff = now();
        $parameters = [
            'locale' => app()->getLocale(),
            'cutoff_at' => $cutoff->toDateTimeString(),
        ];

        $report = GeneratedReport::create([
            'name' => 'All Clients Debt Report (' . $cutoff->format('Y-m-d H:i') . ')',
            'type' => 'client_debt',
            'format' => $request->format,
            'parameters' => $parameters,
            'last_included_ledger_id' => $this->ledgerBoundaryService->snapshotLastIncludedLedgerId('client_debt', $parameters, $cutoff),
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Report generation started in the background. Please wait.');
    }

    public function exportClientDebt(Request $request, Client $client)
    {
        $request->validate([
            'format' => 'required|in:png,jpg',
        ]);

        $cutoff = now();
        $parameters = [
            'client_id' => $client->id,
            'locale' => app()->getLocale(),
            'cutoff_at' => $cutoff->toDateTimeString(),
        ];

        $report = GeneratedReport::create([
            'name' => 'Debt Report: ' . $client->name . ' (' . $cutoff->format('Y-m-d H:i') . ')',
            'type' => 'single_client_debt',
            'format' => $request->format,
            'parameters' => $parameters,
            'last_included_ledger_id' => $this->ledgerBoundaryService->snapshotLastIncludedLedgerId('single_client_debt', $parameters, $cutoff),
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Report generation started for ' . $client->name . '. Please wait.');
    }

    public function regenerate(GeneratedReport $report)
    {
        if ($report->status === 'pending') {
            return redirect()->route('admin.reports.index')
                ->with('success', __('Report generation is already pending.'));
        }

        $lastIncludedLedgerId = $this->ledgerBoundaryService->resolveReportLastIncludedLedgerId($report);
        $parameters = $report->parameters ?? [];
        $cutoff = $this->ledgerBoundaryService->resolveReportCutoffAt($report);

        if (empty($parameters['cutoff_at']) && $cutoff !== null) {
            $parameters['cutoff_at'] = $cutoff->toDateTimeString();
        }

        $report->forceFill([
            'parameters' => $parameters,
            'last_included_ledger_id' => $lastIncludedLedgerId,
            'status' => 'pending',
            'error_message' => null,
        ])->save();

        GenerateClientDebtReport::dispatch($report->fresh());

        return redirect()->route('admin.reports.index')
            ->with('success', __('Report regeneration started in the background. Please wait.'));
    }
}
