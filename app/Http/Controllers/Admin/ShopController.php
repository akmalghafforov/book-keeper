<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Store a newly created shop in storage.
     * Handles both standard form submission and AJAX requests.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        $shop = Shop::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'shop' => $shop,
                'message' => __('Shop created successfully.'),
            ]);
        }

        return redirect()->back()->with('success', __('Shop created successfully.'));
    }
}
