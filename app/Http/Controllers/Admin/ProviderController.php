<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Provider::withBalance()->latest();

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]);
            });
        }

        $providers = $query->paginate(10)->withQueryString();

        return view('admin.providers.index', compact('providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.providers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        Provider::create($validated);

        return redirect()->route('admin.providers.index')
            ->with('success', __('Provider created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
        $providerLedgers = $provider->providerLedgers()
            ->with(['product', 'distribution'])
            ->inReverseOperationOrder()
            ->paginate(10);

        return view('admin.providers.show', compact('provider', 'providerLedgers'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Provider $provider)
    {
        return view('admin.providers.edit', compact('provider'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Provider $provider)
    {
        $validated = $request->validate($this->rules());

        $provider->update($validated);

        return redirect()->route('admin.providers.index')
            ->with('success', __('Provider updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        $provider->defaultProducts()->update(['default_provider_id' => null]);
        $provider->delete();

        return redirect()->route('admin.providers.index')
            ->with('success', __('Provider deleted successfully.'));
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
