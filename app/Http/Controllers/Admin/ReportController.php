<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Jobs\GenerateClientDebtReport;
use Illuminate\Http\Request;
use Imagick;

class ReportController extends Controller
{
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
            'format' => 'required|in:png,jpg',
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
}
