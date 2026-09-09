<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateClientDebtReport;
use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\GeneratedReport;
use App\Models\ProviderLedger;
use App\Services\GeneratedReportLedgerBoundaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
            'name' => 'All Clients Debt Report ('.$cutoff->format('Y-m-d H:i').')',
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
        $parameters = $this->clientReportParameters($client, [
            'locale' => app()->getLocale(),
            'cutoff_at' => $cutoff->toDateTimeString(),
        ]);

        $report = GeneratedReport::create([
            'name' => 'Debt Report: '.$client->name.' ('.$cutoff->format('Y-m-d H:i').')',
            'type' => 'single_client_debt',
            'format' => $request->format,
            'parameters' => $parameters,
            'last_included_ledger_id' => $this->ledgerBoundaryService->snapshotLastIncludedLedgerId('single_client_debt', $parameters, $cutoff),
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Report generation started for '.$client->name.'. Please wait.');
    }

    public function exportClientDebtRange(Request $request, Client $client)
    {
        $validated = $request->validate([
            'format' => 'required|in:png,jpg',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $rangeStart = Carbon::parse($validated['start_date'])->startOfDay();
        $rangeEnd = $request->filled('end_date')
            ? Carbon::parse($validated['end_date'])->startOfDay()
            : null;
        $cutoff = now();
        $parameters = $this->clientReportParameters($client, [
            'locale' => app()->getLocale(),
            'cutoff_at' => $cutoff->toDateTimeString(),
            'range_start_date' => $rangeStart->toDateString(),
            'range_end_date' => $rangeEnd?->toDateString(),
        ]);
        $rangeLabel = $rangeEnd
            ? $rangeStart->format('Y-m-d').' - '.$rangeEnd->format('Y-m-d')
            : 'from '.$rangeStart->format('Y-m-d');

        $report = GeneratedReport::create([
            'name' => 'Debt Range Report: '.$client->name.' ('.$rangeLabel.')',
            'type' => 'single_client_debt_range',
            'format' => $validated['format'],
            'parameters' => $parameters,
            'last_included_ledger_id' => $this->ledgerBoundaryService->snapshotLastIncludedLedgerId('single_client_debt_range', $parameters, $cutoff),
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Date range report generation started for '.$client->name.'. Please wait.');
    }

    public function exportOperationDebt(Request $request, DebtLedger $operation)
    {
        $validated = $request->validate([
            'format' => 'required|in:png,jpg',
        ]);

        $operation->loadMissing('client');

        $rangeStart = Carbon::parse($operation->transaction_date ?? $operation->created_at)->startOfDay();
        $cutoff = now();
        $parameters = $this->clientReportParameters($operation->client, [
            'locale' => app()->getLocale(),
            'cutoff_at' => $cutoff->toDateTimeString(),
            'range_start_date' => $rangeStart->toDateString(),
            'range_end_date' => null,
            'range_start_ledger_id' => $operation->id,
        ]);

        $report = GeneratedReport::create([
            'name' => 'Debt Report: '.$operation->client->name.' (from operation #'.$operation->id.')',
            'type' => 'single_client_debt_range',
            'format' => $validated['format'],
            'parameters' => $parameters,
            'last_included_ledger_id' => $this->ledgerBoundaryService->snapshotLastIncludedLedgerId('single_client_debt_range', $parameters, $cutoff),
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Debt report generation started from the selected operation for '.$operation->client->name.'. Please wait.');
    }

    public function exportProviderLedgerDebt(Request $request, ProviderLedger $providerLedger)
    {
        $validated = $request->validate([
            'format' => 'required|in:png,jpg',
        ]);

        $providerLedger->loadMissing('provider');

        $rangeStart = Carbon::parse($providerLedger->provider_received_at ?? $providerLedger->transaction_date ?? $providerLedger->created_at)->startOfDay();
        $cutoff = now();
        $parameters = [
            'provider_id' => $providerLedger->provider_id,
            'locale' => app()->getLocale(),
            'cutoff_at' => $cutoff->toDateTimeString(),
            'range_start_date' => $rangeStart->toDateString(),
            'range_end_date' => null,
            'range_start_provider_ledger_id' => $providerLedger->id,
        ];

        $report = GeneratedReport::create([
            'name' => 'Debt Report: '.$providerLedger->provider->name.' (from provider ledger #'.$providerLedger->id.')',
            'type' => 'single_provider_debt_range',
            'format' => $validated['format'],
            'parameters' => $parameters,
            'last_included_ledger_id' => $this->ledgerBoundaryService->snapshotLastIncludedLedgerId('single_provider_debt_range', $parameters, $cutoff),
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Debt report generation started from the selected provider ledger for '.$providerLedger->provider->name.'. Please wait.');
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

    public function shareData(GeneratedReport $report)
    {
        $this->ensureShareableClientReport($report);

        $parameters = $report->parameters ?? [];
        $client = isset($parameters['client_id']) ? Client::withTrashed()->find($parameters['client_id']) : null;
        $clientName = $parameters['client_name'] ?? $client?->name;

        abort_if(blank($clientName), 422, __('This report has no client available for sharing.'));

        $generatedAt = $report->report_generated_at?->format('Y-m-d H:i') ?? $report->created_at?->format('Y-m-d H:i');
        $message = __('Debt report for :client (report #:serial, generated :generatedAt).', [
            'client' => $clientName,
            'serial' => $report->formatted_serial_number,
            'generatedAt' => $generatedAt,
        ]);

        if ($context = $this->shareContext($parameters)) {
            $message .= "\n".$context;
        }

        return response()->json([
            'client_name' => $clientName,
            'phone' => $parameters['client_phone'] ?? $client?->phone,
            'message' => $message,
            'image_url' => route('admin.reports.image', $report),
            'file_name' => 'debt-report-'.$report->formatted_serial_number.'.'.$report->format,
        ]);
    }

    public function image(GeneratedReport $report)
    {
        $this->ensureReportImageAvailable($report);

        return $this->reportStorageDisk($report)->response($report->file_path, null, [
            'Content-Type' => $report->format === 'png' ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function clientReportParameters(Client $client, array $parameters): array
    {
        return array_merge($parameters, [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_phone' => $client->phone,
        ]);
    }

    private function ensureShareableClientReport(GeneratedReport $report): void
    {
        abort_unless(in_array($report->type, ['single_client_debt', 'single_client_debt_range'], true), 404);
        $this->ensureReportImageAvailable($report);
    }

    private function ensureReportImageAvailable(GeneratedReport $report): void
    {
        abort_unless($report->status === 'completed' && filled($report->file_path), 404);

        $path = (string) $report->file_path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(
            str_starts_with($path, 'reports/')
            && ! str_contains($path, '..')
            && in_array($extension, ['png', 'jpg', 'jpeg'], true)
            && ($this->reportStorageDisk($report)->exists($path)),
            404,
        );
    }

    private function reportStorageDisk(GeneratedReport $report)
    {
        // Existing reports remain on public storage. New client reports are
        // private, so allow both locations during the transition.
        return Storage::disk('local')->exists((string) $report->file_path)
            ? Storage::disk('local')
            : Storage::disk('public');
    }

    private function shareContext(array $parameters): ?string
    {
        $start = $parameters['range_start_date'] ?? null;
        $end = $parameters['range_end_date'] ?? null;

        if (! $start) {
            return null;
        }

        return $end
            ? __('Report period: :start to :end.', ['start' => $start, 'end' => $end])
            : __('Report period: from :start.', ['start' => $start]);
    }
}
