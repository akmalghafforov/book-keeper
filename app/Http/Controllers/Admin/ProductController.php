<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with(['defaultProvider', 'productCategory'])->latest()->paginate(10);

        return view('admin.products.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $providers = Provider::orderBy('name')->get();
        $productCategories = ProductCategory::orderBy('name')->get();

        return view('admin.products.create', compact('providers', 'productCategories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        Product::create($validated);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load(['defaultProvider', 'productCategory']);

        return view('admin.products.show', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $providers = Provider::orderBy('name')->get();
        $productCategories = ProductCategory::orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'providers', 'productCategories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate($this->rules());

        $product->update($validated);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')],
            'default_unit' => 'nullable|in:per_ton,per_bag,per_piece',
            'buy_price' => 'nullable|numeric|min:0',
            'default_provider_id' => [
                'nullable',
                Rule::exists('providers', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
