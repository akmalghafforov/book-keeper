<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DebtLedger;
use App\Services\PaymentCurrencyConverter;
use App\Services\PotentialDuplicateDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DebtLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PotentialDuplicateDetector $potentialDuplicateDetector)
    {
        $query = DebtLedger::with('client')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', '%'.$search.'%')
                    ->orWhere('reference_id', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $potentialDuplicateGroups = $potentialDuplicateDetector->detectDebtLedgers(
            (clone $query)->get()
        );

        $debtLedgers = $query->paginate(15)->withQueryString();
        $clients = Client::orderBy('name')->get();

        return view('admin.debt-ledgers.index', compact('debtLedgers', 'clients', 'potentialDuplicateGroups'));
    }

    public function resolvePotentialDuplicate(Request $request, PotentialDuplicateDetector $potentialDuplicateDetector)
    {
        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:2'],
            'record_ids.*' => ['integer', 'distinct', 'exists:debt_ledgers,id'],
        ]);

        $records = DebtLedger::with('client')
            ->whereKey($validated['record_ids'])
            ->get();

        $resolved = $potentialDuplicateDetector->resolveDebtLedgers($records, $request->user()?->id);

        return back()->with(
            'success',
            $resolved
                ? 'Potential duplicate group marked as resolved.'
                : 'The selected records no longer match a potential duplicate group.',
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $clients = Client::orderBy('name')->get();
        $selectedClientId = $request->query('client_id');

        return view('admin.debt-ledgers.create', compact('clients', 'selectedClientId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, PaymentCurrencyConverter $paymentCurrencyConverter)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'type' => 'required|in:charge,payment,credit_note',
            'payment_method' => ['nullable', 'required_if:type,payment', Rule::enum(PaymentMethod::class)],
            'payment_purpose' => ['exclude_unless:type,payment', 'nullable', Rule::enum(PaymentPurpose::class)],
            'payer_name' => ['exclude_unless:payment_purpose,on_behalf_of', 'required', 'string', 'max:255'],
            'amount' => 'required|numeric|min:0.01',
            ...$paymentCurrencyConverter->rules(),
            'transaction_date' => 'required|date_format:d/n/Y,d/m/Y',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['transaction_date'] = Carbon::createFromFormat('d/n/Y', $validated['transaction_date'])->format('Y-m-d');
        $validated['payment_method'] = $validated['type'] === 'payment' ? $validated['payment_method'] : null;
        $this->normalizePaymentPurpose($validated);
        $validated = $paymentCurrencyConverter->convert($validated);

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
            'payment_method' => ['nullable', 'required_if:type,payment', Rule::enum(PaymentMethod::class)],
            'payment_purpose' => ['exclude_unless:type,payment', 'nullable', Rule::enum(PaymentPurpose::class)],
            'payer_name' => ['exclude_unless:payment_purpose,on_behalf_of', 'required', 'string', 'max:255'],
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date_format:d/n/Y,d/m/Y',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['transaction_date'] = Carbon::createFromFormat('d/n/Y', $validated['transaction_date'])->format('Y-m-d');
        $validated['payment_method'] = $validated['type'] === 'payment' ? $validated['payment_method'] : null;
        $this->normalizePaymentPurpose($validated);

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

    private function normalizePaymentPurpose(array &$validated): void
    {
        if ($validated['type'] !== 'payment') {
            $validated['payment_purpose'] = null;
            $validated['payer_name'] = null;

            return;
        }

        $validated['payment_purpose'] = $validated['payment_purpose'] ?? null;
        $validated['payer_name'] = $validated['payment_purpose'] === PaymentPurpose::OnBehalfOf->value
            ? $validated['payer_name']
            : null;
    }
}
