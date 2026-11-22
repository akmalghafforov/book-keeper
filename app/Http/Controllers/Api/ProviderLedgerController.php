<?php

namespace App\Http\Controllers\Api;

use App\Models\ProviderLedger;
use App\Services\ManualProviderLedgerEntry;
use App\Services\PaymentCurrencyConverter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProviderLedgerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'provider_id' => 'nullable|integer',
            'type' => 'nullable|in:charge,payment',
            'search' => 'nullable|string',
            'provider_date_from' => 'nullable|date_format:Y-m-d',
            'provider_date_to' => 'nullable|date_format:Y-m-d',
            'tonnage' => 'nullable|numeric',
            'vehicle_number' => 'nullable|string',
            'paid_amount' => 'nullable|numeric',
            'per_page' => 'nullable|integer|min:1',
        ]);

        $entries = $this->entries()
            ->inReverseOperationOrder()
            ->filterLedger($filters)
            ->paginate(min($filters['per_page'] ?? 20, 100));

        return response()->json($this->collection(
            $entries,
            'provider-ledger',
            fn (ProviderLedger $entry) => $this->entryResource($entry)['data'],
        ));
    }

    public function show(string $providerLedger): JsonResponse
    {
        return $this->entryResponse($providerLedger);
    }

    public function store(Request $request, PaymentCurrencyConverter $converter): JsonResponse
    {
        return $this->idempotent($request, function () use ($request, $converter) {
            $entry = ProviderLedger::create($this->manualAttributes($request, $converter));

            return $this->entryResponse($entry->getKey(), 201);
        });
    }

    public function update(Request $request, string $providerLedger): Response
    {
        return $this->change($request, $providerLedger, function (ProviderLedger $entry) use ($request) {
            $entry->fill($this->manualAttributes($request));
            // Advance the ETag even for no-op saves and multiple writes in the same second.
            $entry->api_revision++;
            $entry->save();

            return $this->entryResponse($entry->getKey());
        });
    }

    public function destroy(Request $request, string $providerLedger): Response
    {
        return $this->change($request, $providerLedger, function (ProviderLedger $entry) {
            $entry->delete();

            return response()->noContent();
        });
    }

    private function change(Request $request, string $id, Closure $write): Response
    {
        return DB::transaction(function () use ($request, $id, $write) {
            $query = ProviderLedger::whereKey($id);

            if (DB::connection()->getDriverName() === 'sqlite') {
                // SQLite ignores FOR UPDATE. A write before the SELECT takes its writer lock.
                (clone $query)->toBase()->update(['updated_at' => DB::raw('updated_at')]);
            }

            $entry = $query->lockForUpdate()->firstOrFail();
            if ($entry->origin === 'distribution' || $entry->distribution_id !== null) {
                return response()->json([
                    'message' => 'Distribution-derived entries must be changed through /api/v1/distributions/{id}.',
                ], 409);
            }

            return $this->requireMatch($request, $entry) ?? $write($entry);
        }, 5);
    }

    private function manualAttributes(Request $request, ?PaymentCurrencyConverter $converter = null): array
    {
        $validated = $request->validate([
            ...ManualProviderLedgerEntry::rules($converter),
            'provider_id' => ['required', 'integer', Rule::exists('providers', 'id')->whereNull('deleted_at')],
            'provider_received_at' => ['required', ...ManualProviderLedgerEntry::OFFSET_TIMESTAMP],
        ]);
        $receivedAt = Date::parse($validated['provider_received_at'])->setTimezone(config('app.timezone'));
        $validated = $converter?->convert($validated) ?? $validated;

        return [...ManualProviderLedgerEntry::attributes($validated, $receivedAt), 'origin' => 'manual'];
    }

    private function entries(): Builder
    {
        return ProviderLedger::with(['provider:id,name', 'product:id,name', 'distribution:id,distribution_date'])
            ->withRunningBalance()
            ->withCasts(['running_balance' => 'decimal:4']);
    }

    private function entryResponse(int|string $id, int $status = 200): JsonResponse
    {
        $entry = $this->entries()->findOrFail($id);

        return response()->json($this->entryResource($entry), $status)->header('ETag', $this->etag($entry));
    }

    protected function etag(Model $model): string
    {
        // Running balances can change when a different entry is written.
        $stored = Arr::except($model->getRawOriginal(), ['running_balance']);

        return '"'.hash('sha256', json_encode($stored, JSON_THROW_ON_ERROR)).'"';
    }

    private function entryResource(ProviderLedger $entry): array
    {
        return $this->resource($entry, 'provider-ledger', [
            'provider_id' => (string) $entry->provider_id,
            'provider' => $entry->provider ? ['id' => (string) $entry->provider->id, 'name' => $entry->provider->name] : null,
            'product_id' => $entry->product_id === null ? null : (string) $entry->product_id,
            'product' => $entry->product ? ['id' => (string) $entry->product->id, 'name' => $entry->product->name] : null,
            'distribution_id' => $entry->distribution_id === null ? null : (string) $entry->distribution_id,
            'distribution' => $entry->distribution ? [
                'id' => (string) $entry->distribution->id,
                'distribution_date' => $entry->distribution->distribution_date?->toDateString(),
            ] : null,
            'type' => $entry->type,
            'origin' => $entry->origin,
            'payment_method' => $entry->payment_method?->value,
            'payment_purpose' => $entry->payment_purpose?->value,
            'payer_name' => $entry->payer_name,
            'car_number' => $entry->car_number,
            'quantity' => $entry->quantity,
            'buy_price' => $entry->buy_price,
            'amount' => $entry->amount,
            'transaction_date' => $entry->transaction_date?->toDateString(),
            'provider_received_at' => $entry->provider_received_at?->toIso8601String(),
            'notes' => $entry->notes,
            'running_balance' => $entry->running_balance,
        ]);
    }
}
