<?php

namespace App\Http\Controllers\Api;

use App\Models\DebtLedger;
use App\Services\PaymentCurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DebtLedgerController extends ApiController
{
    public function index(Request $r)
    {
        $q = DebtLedger::with('client')->orderByDesc('transaction_date')->orderByDesc('id');
        foreach (['client_id', 'type'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->$f);
            }
        } if ($r->filled('search')) {
            $q->where(fn ($x) => $x->where('notes', 'like', '%'.$r->search.'%')->orWhere('reference_id', 'like', '%'.$r->search.'%'));
        } foreach (['date_from' => '>=', 'date_to' => '<='] as $f => $op) {
            if ($r->filled($f)) {
                $q->whereDate('transaction_date', $op, $r->$f);
            }
        }

return response()->json($this->collection($q->paginate(min((int) $r->input('per_page', 20), 100)), 'debt-ledger', fn ($m) => $this->item($m)));
    }

    public function show(DebtLedger $debtLedger)
    {
        return response()->json($this->resource($debtLedger, 'debt-ledger', $this->attrs($debtLedger)))->header('ETag', $this->etag($debtLedger));
    }

    public function store(Request $r, PaymentCurrencyConverter $converter)
    {
        return $this->idempotent($r, function () use ($r, $converter) {
            $m = DebtLedger::create($this->data($r, $converter, true));

            return response()->json($this->resource($m, 'debt-ledger', $this->attrs($m)), 201)->header('ETag', $this->etag($m));
        });
    }

    public function update(Request $r, DebtLedger $debtLedger)
    {
        if ($x = $this->requireMatch($r, $debtLedger)) {
            return $x;
        } if ($debtLedger->origin === 'distribution') {
            return response()->json(['message' => 'Distribution-derived entries must be changed through their distribution.'], 409);
        } $debtLedger->update($this->data($r, null, false));

        return response()->json($this->resource($debtLedger->fresh(), 'debt-ledger', $this->attrs($debtLedger->fresh())))->header('ETag', $this->etag($debtLedger->fresh()));
    }

    public function destroy(Request $r, DebtLedger $debtLedger)
    {
        if ($x = $this->requireMatch($r, $debtLedger)) {
            return $x;
        } if ($debtLedger->origin === 'distribution') {
            return response()->json(['message' => 'Distribution-derived entries must be changed through their distribution.'], 409);
        } $debtLedger->delete();

        return response()->noContent();
    }

    private function data(Request $r, ?PaymentCurrencyConverter $converter, bool $create): array
    {
        $rules = ['client_id' => 'required|exists:clients,id', 'type' => 'required|in:charge,payment,credit_note', 'amount' => 'required|numeric|gt:0', 'transaction_date' => 'required|date_format:Y-m-d', 'payment_method' => ['nullable', 'required_if:type,payment', Rule::in(['cash', 'card', 'ds', 'eo', 'alif'])], 'payment_purpose' => ['nullable', Rule::in(['on_behalf_of', 'vehicle_and_labor', 'vehicle', 'labor', 'other'])], 'payer_name' => 'nullable|string|max:255', 'reference_id' => 'nullable|integer', 'notes' => 'nullable|string'];
        if ($create) {
            $rules = array_merge($rules, $converter->rules());
        } $v = $r->validate($rules);
        if ($v['type'] !== 'payment') {
            foreach (['payment_method', 'payment_purpose', 'payer_name'] as $f) {
                $v[$f] = null;
            }
        } elseif (($v['payment_purpose'] ?? null) !== 'on_behalf_of') {
            $v['payer_name'] = null;
        } $v['origin'] = 'manual';

        return $converter ? $converter->convert($v) : $v;
    }

    private function attrs(DebtLedger $m): array
    {
        return ['client_id' => (string) $m->client_id, 'client' => ['id' => (string) $m->client_id, 'name' => $m->client?->name], 'type' => $m->type, 'amount' => (string) $m->amount, 'transaction_date' => $m->transaction_date?->toDateString(), 'payment_method' => $m->payment_method?->value, 'payment_purpose' => $m->payment_purpose?->value, 'payer_name' => $m->payer_name, 'reference_id' => $m->reference_id, 'notes' => $m->notes, 'origin' => $m->origin];
    }

    private function item(DebtLedger $m): array
    {
        return ['type' => 'debt-ledger', 'id' => (string) $m->id, 'attributes' => $this->attrs($m)];
    }
}
