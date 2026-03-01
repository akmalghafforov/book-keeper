<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DebtLedger;
use App\Models\Client;
use Illuminate\Http\Request;

class DebtLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $debtLedgers = DebtLedger::with('client')->latest()->paginate(15);
        return view('admin.debt-ledgers.index', compact('debtLedgers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $clients = Client::orderBy('name')->get();
        return view('admin.debt-ledgers.create', compact('clients'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'type' => 'required|in:charge,payment,credit_note',
            'amount' => 'required|numeric|min:0.01',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        DebtLedger::create($validated);

        return redirect()->route('admin.debt-ledgers.index')
            ->with('success', 'Debt ledger entry created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DebtLedger $debtLedger)
    {
        $debtLedger->load('client');
        return view('admin.debt-ledgers.show', compact('debtLedger'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DebtLedger $debtLedger)
    {
        $clients = Client::orderBy('name')->get();
        return view('admin.debt-ledgers.edit', compact('debtLedger', 'clients'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DebtLedger $debtLedger)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'type' => 'required|in:charge,payment,credit_note',
            'amount' => 'required|numeric|min:0.01',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $debtLedger->update($validated);

        return redirect()->route('admin.debt-ledgers.index')
            ->with('success', 'Debt ledger entry updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DebtLedger $debtLedger)
    {
        $debtLedger->delete();

        return redirect()->route('admin.debt-ledgers.index')
            ->with('success', 'Debt ledger entry deleted successfully.');
    }
}
