<?php

namespace App\Http\Controllers\Api;

use App\Models\Distribution;
use App\Services\DistributionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributionController extends ApiController
{
    public function index(Request $r)
    {
        $q = Distribution::with(['client', 'shop', 'product', 'supplier', 'creditClient'])->latest('distribution_date')->latest('id');
        foreach (['client_id', 'product_id', 'supplier_id', 'quantity_unit'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->$f);
            }
        }foreach (['date_from' => '>=', 'date_to' => '<='] as $f => $op) {
            if ($r->filled($f)) {
                $q->whereDate('distribution_date', $op, $r->$f);
            }
        }

return response()->json($this->collection($q->paginate(min((int) $r->input('per_page', 20), 100)), 'distribution', fn ($m) => $this->item($m)));
    }

    public function show(Distribution $distribution)
    {
        $distribution->load(['client', 'shop', 'product', 'supplier', 'creditClient', 'providerLedger']);

        return response()->json($this->resource($distribution, 'distribution', $this->attrs($distribution)))->header('ETag', $this->etag($distribution));
    }

    public function store(Request $r, DistributionService $service)
    {
        return $this->idempotent($r, function () use ($r, $service) {
            $m = $service->create($this->data($r));

            return response()->json($this->resource($m->fresh(), 'distribution', $this->attrs($m->fresh())), 201)->header('ETag', $this->etag($m->fresh()));
        });
    }

    public function update(Request $r, Distribution $distribution, DistributionService $service)
    {
        if ($x = $this->requireMatch($r, $distribution)) {
            return $x;
        }$m = $service->update($distribution, $this->data($r));

        return response()->json($this->resource($m, 'distribution', $this->attrs($m)))->header('ETag', $this->etag($m));
    }

    public function destroy(Request $r, Distribution $distribution, DistributionService $service)
    {
        if ($x = $this->requireMatch($r, $distribution)) {
            return $x;
        }$service->delete($distribution);

        return response()->noContent();
    }

    private function data(Request $r): array
    {
        $v = $r->validate(['supplier_id' => 'nullable|exists:suppliers,id', 'client_id' => 'required|exists:clients,id', 'shop_id' => 'nullable|exists:shops,id', 'credit_client_id' => 'nullable|exists:clients,id', 'credit_client_price' => 'nullable|required_with:credit_client_id|numeric|min:0', 'product_category_id' => 'required|exists:product_categories,id', 'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('product_category_id', $r->product_category_id)->whereNull('deleted_at'))], 'quantity_unit' => 'required|in:per_ton,per_bag,per_piece', 'quantity' => 'required|numeric|min:0', 'price' => 'required|numeric|min:0', 'provider_buy_price' => 'nullable|numeric|min:0', 'distribution_date' => 'required|date_format:Y-m-d', 'provider_received_at' => 'nullable|date']);
        if (! empty($v['shop_id']) && ! \App\Models\Shop::whereKey($v['shop_id'])->where('client_id', $v['client_id'])->exists()) {
            abort(response()->json(['errors' => ['shop_id' => ['The shop must belong to the client.']]], 422));
        }unset($v['product_category_id']);

        return $v;
    }

    private function attrs(Distribution $m): array
    {
        return ['supplier_id' => $m->supplier_id ? (string) $m->supplier_id : null, 'client_id' => (string) $m->client_id, 'shop_id' => $m->shop_id ? (string) $m->shop_id : null, 'credit_client_id' => $m->credit_client_id ? (string) $m->credit_client_id : null, 'product_id' => (string) $m->product_id, 'quantity_unit' => $m->quantity_unit, 'quantity' => (string) $m->quantity, 'price' => (string) $m->price, 'credit_client_price' => $m->credit_client_price === null ? null : (string) $m->credit_client_price, 'provider_buy_price' => $m->provider_buy_price === null ? null : (string) $m->provider_buy_price, 'subtotal' => (string) $m->subtotal, 'distribution_date' => $m->distribution_date?->toDateString(), 'provider_received_at' => $m->provider_received_at?->toIso8601String(), 'provider_ledger_id' => $m->providerLedger?->id ? (string) $m->providerLedger->id : null];
    }

    private function item(Distribution $m): array
    {
        return ['type' => 'distribution', 'id' => (string) $m->id, 'attributes' => $this->attrs($m)];
    }
}
