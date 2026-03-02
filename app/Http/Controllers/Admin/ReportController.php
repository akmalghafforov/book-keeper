<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Jobs\GenerateClientDebtReport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Imagick;

class ReportController extends Controller
{
    public function index()
    {
        $reports = GeneratedReport::latest()->paginate(10);
        return view('admin.reports.index', compact('reports'));
    }

    public function clientDebt(Request $request)
    {
        $clients = $this->getClientDebtData();

        return view('admin.reports.client-debt', compact('clients'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:pdf,jpg',
        ]);

        $report = GeneratedReport::create([
            'name' => 'All Clients Debt Report (' . now()->format('Y-m-d H:i') . ')',
            'type' => 'client_debt',
            'format' => $request->format,
            'parameters' => ['locale' => app()->getLocale()],
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Report generation started in the background. Please wait.');
    }

    public function exportClientDebt(Request $request, Client $client)
    {
        $request->validate([
            'format' => 'required|in:pdf,jpg',
        ]);

        $report = GeneratedReport::create([
            'name' => 'Debt Report: ' . $client->name . ' (' . now()->format('Y-m-d H:i') . ')',
            'type' => 'single_client_debt',
            'format' => $request->format,
            'parameters' => [
                'client_id' => $client->id,
                'locale' => app()->getLocale(),
            ],
            'status' => 'pending',
        ]);

        GenerateClientDebtReport::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Report generation started for ' . $client->name . '. Please wait.');
    }

    private function getClientDebtData()
    {
        return Client::query()
            ->withSum(['debtLedgers as total_charges' => function ($query) {
                $query->where('type', 'charge');
            }], 'amount')
            ->withSum(['debtLedgers as total_payments' => function ($query) {
                $query->where('type', 'payment');
            }], 'amount')
            ->withSum(['debtLedgers as total_credit_notes' => function ($query) {
                $query->where('type', 'credit_note');
            }], 'amount')
            ->get()
            ->map(function ($client) {
                $client->calculated_total_debt = ($client->total_charges ?? 0) - ($client->total_payments ?? 0) - ($client->total_credit_notes ?? 0);
                return $client;
            })
            ->filter(fn($c) => $c->calculated_total_debt != 0)
            ->sortBy('name');
    }
}
