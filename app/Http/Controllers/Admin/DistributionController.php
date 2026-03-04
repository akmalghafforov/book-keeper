<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Distribution::with(['client', 'shop', 'product', 'supplier']);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('quantity_unit')) {
            $query->where('quantity_unit', $request->quantity_unit);
        }

        if ($request->filled('date_from')) {
            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->date_from)->startOfDay();
            $query->where('distribution_date', '>=', $startDate);
        }

        if ($request->filled('date_to')) {
            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->date_to)->endOfDay();
            $query->where('distribution_date', '<=', $endDate);
        }

        $distributions = $query->latest('distribution_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $clients = Client::orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        return view('admin.distributions.index', compact('distributions', 'clients', 'products', 'suppliers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $clients = Client::with('shops')->get();
        $products = Product::all();
        $suppliers = Supplier::all();
        return view('admin.distributions.create', compact('clients', 'products', 'suppliers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'client_id' => 'required|exists:clients,id',
            'shop_id' => 'nullable|exists:shops,id',
            'credit_client_id' => 'nullable|exists:clients,id',
            'product_id' => 'required|exists:products,id',
            'quantity_unit' => 'required|in:per_ton,per_bag,per_piece',
            'quantity' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'distribution_date' => 'required|date_format:d/m/Y',
        ]);

        $validated['distribution_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['distribution_date'])->format('Y-m-d');
        $validated['subtotal'] = $validated['quantity'] * $validated['price'];

        Distribution::create($validated);

        return redirect()->route('admin.distributions.index')
            ->with('success', 'Distribution created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Distribution $distribution)
    {
        $distribution->load(['client', 'shop', 'product', 'supplier']);
        return view('admin.distributions.show', compact('distribution'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Distribution $distribution)
    {
        $clients = Client::with('shops')->get();
        $products = Product::all();
        $suppliers = Supplier::all();
        return view('admin.distributions.edit', compact('distribution', 'clients', 'products', 'suppliers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Distribution $distribution)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'client_id' => 'required|exists:clients,id',
            'shop_id' => 'nullable|exists:shops,id',
            'credit_client_id' => 'nullable|exists:clients,id',
            'product_id' => 'required|exists:products,id',
            'quantity_unit' => 'required|in:per_ton,per_bag,per_piece',
            'quantity' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'distribution_date' => 'required|date_format:d/m/Y',
        ]);

        $validated['distribution_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['distribution_date'])->format('Y-m-d');
        $validated['subtotal'] = $validated['quantity'] * $validated['price'];

        $distribution->update($validated);

        return redirect()->route('admin.distributions.index')
            ->with('success', 'Distribution updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Distribution $distribution)
    {
        $distribution->delete();

        return redirect()->route('admin.distributions.index')
            ->with('success', 'Distribution deleted successfully.');
    }
}
