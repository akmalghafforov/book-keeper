<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProviderLedger::with(['provider', 'product', 'distribution'])
            ->inReverseOperationOrder();

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', '%'.$search.'%')
                    ->orWhere('car_number', 'like', '%'.$search.'%')
                    ->orWhere('distribution_id', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $providerLedgers = $query->paginate(15)->withQueryString();

        $providers = Provider::orderBy('name')->get();

        return view('admin.provider-ledgers.index', compact('providerLedgers', 'providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $providers = Provider::orderBy('name')->get();
        $selectedProviderId = $request->query('provider_id');

        return view('admin.provider-ledgers.create', compact('providers', 'selectedProviderId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->manualEntryRules());
        $transactionDate = $this->parseManualTransactionDate($validated['transaction_date']);

        ProviderLedger::create([
            'provider_id' => $validated['provider_id'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'car_number' => $validated['type'] === 'charge' ? ($validated['car_number'] ?? null) : null,
            'transaction_date' => $transactionDate->toDateString(),
            'provider_received_at' => $transactionDate->toDateTimeString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $message = $validated['type'] === 'charge'
            ? __('Provider debt recorded successfully.')
            : __('Provider payment recorded successfully.');

        return redirect()->route('admin.provider-ledgers.index')
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProviderLedger $providerLedger)
    {
        $providerLedger->load(['provider', 'product', 'distribution']);

        return view('admin.provider-ledgers.show', compact('providerLedger'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProviderLedger $providerLedger)
    {
        $this->ensureManualEntry($providerLedger);

        $providers = Provider::orderBy('name')->get();

        return view('admin.provider-ledgers.edit', compact('providerLedger', 'providers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProviderLedger $providerLedger)
    {
        $this->ensureManualEntry($providerLedger);

        $validated = $request->validate($this->manualEntryRules());
        $transactionDate = $this->parseManualTransactionDate($validated['transaction_date']);

        $providerLedger->update([
            'provider_id' => $validated['provider_id'],
            'type' => $validated['type'],
            'distribution_id' => null,
            'product_id' => null,
            'car_number' => $validated['type'] === 'charge' ? ($validated['car_number'] ?? null) : null,
            'quantity' => null,
            'buy_price' => null,
            'amount' => $validated['amount'],
            'transaction_date' => $transactionDate->toDateString(),
            'provider_received_at' => $transactionDate->toDateTimeString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.provider-ledgers.index')
            ->with('success', __('Provider ledger entry updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProviderLedger $providerLedger)
    {
        $this->ensureManualEntry($providerLedger);

        $providerLedger->delete();

        return redirect()->route('admin.provider-ledgers.index')
            ->with('success', __('Provider ledger entry deleted successfully.'));
    }

    private function manualEntryRules(): array
    {
        return [
            'provider_id' => 'required|exists:providers,id',
            'type' => ['required', Rule::in(['charge', 'payment'])],
            'amount' => 'required|numeric|min:0.01',
            'car_number' => 'nullable|string|max:50',
            'transaction_date' => 'required|date_format:d/m/Y H:i',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    private function parseManualTransactionDate(string $transactionDate): Carbon
    {
        return Carbon::createFromFormat('d/m/Y H:i', $transactionDate);
    }

    private function ensureManualEntry(ProviderLedger $providerLedger): void
    {
        abort_if($providerLedger->distribution_id !== null, 403);
    }
}
