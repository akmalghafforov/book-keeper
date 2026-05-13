<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DebtLedger;
use App\Models\Client;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function index(Request $request)
    {
        $query = DebtLedger::with(['client', 'distribution.product', 'distribution.supplier'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('car_number')) {
            $carNumber = $request->car_number;
            $query->whereHas('distribution.supplier', function ($q) use ($carNumber) {
                $q->where('car_number', 'like', '%' . $carNumber . '%');
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', '%' . $search . '%')
                  ->orWhere('reference_id', 'like', '%' . $search . '%')
                  ->orWhereHas('client', function($cq) use ($search) {
                      $cq->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('distribution.product', function($pq) use ($search) {
                      $pq->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('distribution.supplier', function($sq) use ($search) {
                      $sq->where('car_number', 'like', '%' . $search . '%');
                  });
            });
        }

        $operations = $query->paginate(100)->withQueryString();
        $this->hydrateOperationBalances($operations);

        $clients = Client::orderBy('name')->get();

        return view('admin.operations.index', compact('operations', 'clients'));
    }

    private function hydrateOperationBalances($operations): void
    {
        $displayedOperations = $operations->getCollection();

        if ($displayedOperations->isEmpty()) {
            return;
        }

        $displayedOperationIds = $displayedOperations->pluck('id')->all();
        $displayedOperationIdLookup = array_flip($displayedOperationIds);
        $balancesByClient = [];

        DebtLedger::query()
            ->whereIn('client_id', $displayedOperations->pluck('client_id')->unique()->all())
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'client_id', 'type', 'amount'])
            ->each(function (DebtLedger $ledger) use (&$balancesByClient, $displayedOperationIdLookup, $displayedOperations): void {
                $balancesByClient[$ledger->client_id] ??= 0.0;
                $balancesByClient[$ledger->client_id] += $ledger->type === 'charge'
                    ? (float) $ledger->amount
                    : - (float) $ledger->amount;

                if (! isset($displayedOperationIdLookup[$ledger->id])) {
                    return;
                }

                $displayedOperations
                    ->firstWhere('id', $ledger->id)
                    ?->setAttribute('balance_after_operation', $balancesByClient[$ledger->client_id]);
            });
    }
}
