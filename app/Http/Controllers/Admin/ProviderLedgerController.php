<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Exceptions\InvalidProviderLedgerWorkbook;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Services\PaymentCurrencyConverter;
use App\Services\ProviderLedgerReconciler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderLedgerController extends Controller
{
    public function compareForm()
    {
        $providers = Provider::orderBy('name')->get();

        return view('admin.provider-ledgers.compare', compact('providers'));
    }

    public function compare(Request $request, ProviderLedgerReconciler $reconciler)
    {
        $validated = $request->validate([
            'provider_id' => [
                'required',
                Rule::exists('providers', 'id')->whereNull('deleted_at'),
            ],
            'date_from' => ['required', 'date_format:d/n/Y,d/m/Y'],
            'date_to' => ['required', 'date_format:d/n/Y,d/m/Y', 'after_or_equal:date_from'],
            'excel_file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:10240'],
        ]);

        $provider = Provider::findOrFail($validated['provider_id']);
        $dateFrom = Carbon::createFromFormat('!d/n/Y', $validated['date_from']);
        $dateTo = Carbon::createFromFormat('!d/n/Y', $validated['date_to']);

        try {
            /** @var UploadedFile $file */
            $file = $validated['excel_file'];
            $result = $reconciler->reconcile($provider, $file, $dateFrom, $dateTo);
        } catch (InvalidProviderLedgerWorkbook $exception) {
            throw ValidationException::withMessages([
                'excel_file' => __($exception->getMessage()),
            ]);
        }

        $providers = Provider::orderBy('name')->get();

        return view('admin.provider-ledgers.compare', compact(
            'providers',
            'provider',
            'dateFrom',
            'dateTo',
            'result',
        ));
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProviderLedger::with(['provider', 'product'])
            ->withRunningBalance()
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

        if ($request->filled('provider_date_from')) {
            $query->whereDate('provider_received_at', '>=', $request->provider_date_from);
        }

        if ($request->filled('provider_date_to')) {
            $query->whereDate('provider_received_at', '<=', $request->provider_date_to);
        }

        if ($request->filled('tonnage')) {
            $query->where('quantity', $request->tonnage);
        }

        if ($request->filled('vehicle_number')) {
            $query->where('car_number', 'like', '%'.$request->vehicle_number.'%');
        }

        if ($request->filled('paid_amount')) {
            $query->where('type', 'payment')
                ->where('amount', $request->paid_amount);
        }

        $providerLedgers = $query->paginate(100)->withQueryString();

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
    public function store(Request $request, PaymentCurrencyConverter $paymentCurrencyConverter)
    {
        $validated = $request->validate($this->manualEntryRules($paymentCurrencyConverter));
        $transactionDate = $this->parseManualTransactionDate($validated['transaction_date']);
        $validated = $paymentCurrencyConverter->convert($validated);

        ProviderLedger::create([
            'provider_id' => $validated['provider_id'],
            'type' => $validated['type'],
            'payment_method' => $validated['type'] === 'payment' ? $validated['payment_method'] : null,
            'payment_purpose' => $this->paymentPurpose($validated),
            'payer_name' => $this->payerName($validated),
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
            'payment_method' => $validated['type'] === 'payment' ? $validated['payment_method'] : null,
            'payment_purpose' => $this->paymentPurpose($validated),
            'payer_name' => $this->payerName($validated),
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

    private function manualEntryRules(?PaymentCurrencyConverter $paymentCurrencyConverter = null): array
    {
        return [
            'provider_id' => 'required|exists:providers,id',
            'type' => ['required', Rule::in(['charge', 'payment'])],
            'payment_method' => ['nullable', 'required_if:type,payment', Rule::enum(PaymentMethod::class)],
            'payment_purpose' => ['exclude_unless:type,payment', 'nullable', Rule::enum(PaymentPurpose::class)],
            'payer_name' => ['exclude_unless:payment_purpose,on_behalf_of', 'required', 'string', 'max:255'],
            'amount' => 'required|numeric|min:0.01',
            ...($paymentCurrencyConverter?->rules() ?? []),
            'car_number' => 'nullable|string|max:50',
            'transaction_date' => 'required|date_format:d/n/Y H:i,d/m/Y H:i',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    private function parseManualTransactionDate(string $transactionDate): Carbon
    {
        return Carbon::createFromFormat('d/n/Y H:i', $transactionDate);
    }

    private function paymentPurpose(array $validated): ?string
    {
        return $validated['type'] === 'payment' ? ($validated['payment_purpose'] ?? null) : null;
    }

    private function payerName(array $validated): ?string
    {
        return $this->paymentPurpose($validated) === PaymentPurpose::OnBehalfOf->value
            ? $validated['payer_name']
            : null;
    }

    private function ensureManualEntry(ProviderLedger $providerLedger): void
    {
        abort_if($providerLedger->distribution_id !== null, 403);
    }
}
