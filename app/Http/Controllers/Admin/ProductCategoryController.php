<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $productCategories = ProductCategory::withCount('products')->orderBy('name')->paginate(15);

        return view('admin.product-categories.index', compact('productCategories'));
    }

    public function create()
    {
        return view('admin.product-categories.create');
    }

    public function store(Request $request)
    {
        ProductCategory::create($request->validate($this->rules()));

        return redirect()->route('admin.product-categories.index')->with('success', __('Product category created successfully.'));
    }

    public function show(ProductCategory $productCategory)
    {
        $productCategory->load(['products' => fn ($query) => $query->orderBy('name')]);

        return view('admin.product-categories.show', compact('productCategory'));
    }

    public function edit(ProductCategory $productCategory)
    {
        return view('admin.product-categories.edit', compact('productCategory'));
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validate($this->rules($productCategory)));

        return redirect()->route('admin.product-categories.index')->with('success', __('Product category updated successfully.'));
    }

    public function products(ProductCategory $productCategory)
    {
        return response()->json($productCategory->products()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'default_unit', 'default_provider_id', 'buy_price'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'default_unit' => $product->default_unit,
                'default_provider_id' => $product->default_provider_id,
                'buy_price' => $product->buy_price,
                'has_default_provider' => $product->default_provider_id !== null,
            ]));
    }

    private function rules(?ProductCategory $productCategory = null): array
    {
        return ['name' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'name')->ignore($productCategory)]];
    }
}
