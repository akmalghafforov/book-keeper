<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Client::withBalance()->latest();

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('client_id')) {
            $query->where('id', $request->input('client_id'));
        }

        if ($request->filled('debt_status')) {
            $status = $request->input('debt_status');
            if ($status === 'with_debt') {
                $query->having('balance', '>', 0);
            } elseif ($status === 'no_debt') {
                $query->having('balance', '<=', 0);
            }
        }

        $clients = $query->paginate(10)->withQueryString();
        $allClients = Client::orderBy('name')->get(['id', 'name']);

        return view('admin.clients.index', compact('clients', 'allClients'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.clients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $client = Client::create($validated);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Client created successfully.',
                'client' => $client
            ]);
        }

        return redirect()->route('admin.clients.index')
            ->with('success', 'Client created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        $client->load(['distributions.product', 'debtLedgers']);
        return view('admin.clients.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client)
    {
        return view('admin.clients.edit', compact('client'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $client->update($validated);

        return redirect()->route('admin.clients.index')
            ->with('success', 'Client updated successfully.');
    }
}
