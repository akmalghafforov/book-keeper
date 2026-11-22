<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidProviderLedgerWorkbook;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Services\ManualProviderLedgerEntry;
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
            ->inReverseOperationOrder()
            ->filterLedger($request->all());

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
        $validated = $request->validate([
            ...ManualProviderLedgerEntry::rules($paymentCurrencyConverter),
            'transaction_date' => 'required|date_format:d/n/Y H:i,d/m/Y H:i',
        ]);
        $transactionDate = $this->parseManualTransactionDate($validated['transaction_date']);
        $validated = $paymentCurrencyConverter->convert($validated);

        ProviderLedger::create(ManualProviderLedgerEntry::attributes($validated, $transactionDate));

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

        $validated = $request->validate([
            ...ManualProviderLedgerEntry::rules(),
            'transaction_date' => 'required|date_format:d/n/Y H:i,d/m/Y H:i',
        ]);
        $transactionDate = $this->parseManualTransactionDate($validated['transaction_date']);

        $providerLedger->update(ManualProviderLedgerEntry::attributes($validated, $transactionDate));

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

    private function parseManualTransactionDate(string $transactionDate): Carbon
    {
        return Carbon::createFromFormat('d/n/Y H:i', $transactionDate);
    }

    private function ensureManualEntry(ProviderLedger $providerLedger): void
    {
        abort_if($providerLedger->distribution_id !== null, 403);
    }
}
