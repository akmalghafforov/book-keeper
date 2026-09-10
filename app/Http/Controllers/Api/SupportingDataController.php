<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Provider;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupportingDataController extends ApiController
{
    private function compact($m, string $type, array $fields): array
    {
        return ['type' => $type, 'id' => (string) $m->id, 'attributes' => collect($fields)->mapWithKeys(fn ($f) => [$f => (string) ($m->$f ?? '')])->all()];
    }

    public function clients(Request $r)
    {
        $q = Client::withBalance()->with($r->boolean('include') ? ['shops'] : [])->orderBy('name');
        if ($r->filled('search')) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->search.'%')->orWhere('phone', 'like', '%'.$r->search.'%'));
        }

return response()->json($this->collection($q->paginate(min((int) $r->input('per_page', 20), 100)), 'client', fn ($m) => $this->compact($m, 'client', ['name', 'phone', 'balance'])));
    }

    public function storeClient(Request $r)
    {
        $m = Client::create($r->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:50']));

        return response()->json($this->resource($m, 'client', ['name' => $m->name, 'phone' => $m->phone]), 201);
    }

    public function shops(Client $client)
    {
        return response()->json(['data' => $client->shops->map(fn ($m) => $this->compact($m, 'shop', ['client_id', 'name', 'address']))]);
    }

    public function storeShop(Request $r, Client $client)
    {
        $m = $client->shops()->create($r->validate(['name' => 'required|string|max:255', 'address' => 'nullable|string|max:255']));

        return response()->json($this->resource($m, 'shop', ['client_id' => (string) $m->client_id, 'name' => $m->name, 'address' => $m->address]), 201);
    }

    public function categories()
    {
        return response()->json(['data' => ProductCategory::orderByDesc('usage_priority')->orderBy('name')->orderBy('id')->get()->map(fn ($m) => $this->compact($m, 'product-category', ['name', 'usage_priority']))]);
    }

    public function products(Request $r)
    {
        $r->validate(['product_category_id' => 'required|exists:product_categories,id']);

        return response()->json(['data' => Product::with('defaultProvider')->where('product_category_id', $r->product_category_id)->orderBy('name')->get()->map(fn ($m) => ['type' => 'product', 'id' => (string) $m->id, 'attributes' => ['name' => $m->name, 'product_category_id' => (string) $m->product_category_id, 'default_unit' => $m->default_unit, 'default_provider_id' => $m->default_provider_id ? (string) $m->default_provider_id : null, 'buy_price' => $m->buy_price, 'has_default_provider' => (bool) $m->default_provider_id]])]);
    }

    public function suppliers(Request $r)
    {
        $q = Supplier::query();
        if ($r->filled('search')) {
            $q->where('car_number', 'like', '%'.$r->search.'%');
        }

return response()->json($this->collection($q->paginate(min((int) $r->input('per_page', 20), 100)), 'supplier', fn ($m) => $this->compact($m, 'supplier', ['car_number', 'car_color'])));
    }

    public function storeSupplier(Request $r)
    {
        $m = Supplier::create($r->validate(['car_number' => 'required|string|max:50', 'car_color' => 'nullable|string|max:50']));

        return response()->json($this->resource($m, 'supplier', ['car_number' => $m->car_number, 'car_color' => $m->car_color]), 201);
    }

    public function providers(Request $r)
    {
        $q = Provider::query();
        if ($r->filled('search')) {
            $q->where('name', 'like', '%'.$r->search.'%');
        }

return response()->json($this->collection($q->paginate(min((int) $r->input('per_page', 20), 100)), 'provider', fn ($m) => $this->compact($m,'provider',['name'])));
    }
}
