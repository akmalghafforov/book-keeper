<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;

class DistributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $distributions = Distribution::with(['client', 'product', 'supplier'])
            ->latest()
            ->paginate(10);
        return view('admin.distributions.index', compact('distributions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $clients = Client::all();
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
        $distribution->load(['client', 'product', 'supplier']);
        return view('admin.distributions.show', compact('distribution'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Distribution $distribution)
    {
        $clients = Client::all();
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
