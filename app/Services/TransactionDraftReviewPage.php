<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\TransactionDraft;
use Illuminate\Support\Collection;

class TransactionDraftReviewPage
{
    public function forQueue(string $tab, ?string $kind): array
    {
        $pending = $tab === 'pending';
        $drafts = TransactionDraft::query()->where('status', $tab)
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->orderBy($pending ? 'created_at' : 'updated_at', $pending ? 'asc' : 'desc')
            ->orderBy('id', $pending ? 'asc' : 'desc')
            ->paginate(10)
            ->appends(array_filter(['tab' => $tab, 'kind' => $kind]));

        $fields = $pending
            ? $drafts->getCollection()->pluck('kind')->unique()->mapWithKeys(fn ($kind) => [$kind => $this->fields($kind)])
            : collect();

        return [
            'drafts' => $drafts,
            'fields' => $fields,
            'choices' => $fields->map(fn ($definitions) => collect($definitions)
                ->map(fn ($definition) => $definition[1] ?? null)
                ->filter(fn ($options) => $options !== null)),
            'pendingCount' => $pending && $kind === null ? $drafts->total() : TransactionDraft::where('status', 'pending')->count(),
            'catalogNames' => $pending ? [] : $this->reviewedCatalog($drafts->getCollection()),
        ];
    }

    private function reviewedCatalog(Collection $drafts): array
    {
        $catalog = [];
        foreach (['client_id' => Client::class, 'provider_id' => Provider::class, 'product_id' => Product::class] as $field => $model) {
            $ids = $drafts->pluck("current_fields.$field")->filter()->unique();
            if ($ids->isNotEmpty()) {
                $catalog[$field] = $model::whereKey($ids->all())->pluck('name', 'id');
            }
        }

        return $catalog;
    }

    private function fields(string $kind): array
    {
        $clients = fn () => Client::orderBy('name')->get(['id', 'name'])->toArray();
        if ($kind === 'distribution') {
            $clientOptions = $clients();

            return [
                'client_id' => [__('Client'), $clientOptions],
                'shop_id' => [__('Shop (Optional)'), Shop::orderBy('name')->get(['id', 'name', 'client_id'])->toArray()],
                'product_category_id' => [__('Product Category'), ProductCategory::orderBy('name')->get(['id', 'name'])->toArray()],
                'product_id' => [__('Product'), Product::orderBy('name')->get(['id', 'name', 'product_category_id'])->toArray()],
                'distribution_date' => [__('Date'), null, 'YYYY-MM-DD'],
                'quantity_unit' => [__('Unit'), $this->options(['per_ton', 'per_bag', 'per_piece'])],
                'quantity' => [__('Quantity')],
                'price' => [__('Price')],
                'supplier_id' => [__('Supplier'), Supplier::orderBy('car_number')->get(['id', 'car_number'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->car_number])->all()],
                'credit_client_id' => [__('Credit Client (Optional)'), $clientOptions],
                'credit_client_price' => [__('Credit Client Price')],
                'provider_buy_price' => [__('Provider Price (Optional)')],
                'provider_received_at' => [__('Provider Date & Time'), null, '2026-11-19T09:10:00+05:00'],
            ];
        }

        $provider = $kind === 'provider_ledger';
        $purposes = array_filter(PaymentPurpose::cases(), fn (PaymentPurpose $purpose) => ! $provider || $purpose !== PaymentPurpose::Other);

        return [
            ...($provider ? [
                'provider_id' => [__('Provider'), Provider::orderBy('name')->get(['id', 'name'])->toArray()],
                'provider_received_at' => [__('Provider Date & Time'), null, '2026-11-19T09:10:00+05:00'],
            ] : [
                'client_id' => [__('Client'), $clients()],
                'transaction_date' => [__('Transaction Date'), null, 'YYYY-MM-DD'],
            ]),
            'type' => [__('Type'), $this->options($provider ? ['charge', 'payment'] : ['charge', 'payment', 'credit_note'])],
            'amount' => [__('Amount')],
            'currency' => [__('Currency'), $this->options(PaymentCurrencyConverter::CURRENCIES)],
            'exchange_rate' => [__('Exchange Rate')],
            'payment_method' => [__('Payment Method'), array_map(fn (PaymentMethod $method) => ['id' => $method->value, 'name' => __($method->label())], PaymentMethod::cases())],
            'payment_purpose' => [__('Payment Purpose'), array_values(array_map(fn (PaymentPurpose $purpose) => ['id' => $purpose->value, 'name' => $purpose->label(__('Payer Name'))], $purposes))],
            'payer_name' => [__('Payer Name')],
            ...($provider ? ['car_number' => [__('Car Number (Optional)')]] : ['reference_id' => [__('Reference ID (Optional)')]]),
            'notes' => [__('Notes (Optional)')],
        ];
    }

    private function options(array $values): array
    {
        return array_map(fn (string $value) => ['id' => $value, 'name' => __($value)], $values);
    }
}
