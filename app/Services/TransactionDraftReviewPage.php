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
use App\Models\TransactionDraftImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TransactionDraftReviewPage
{
    private const CEMENT_FAMILIES = [
        'Хуаксин' => ['хуак', 'хуакс', 'хуаксин', 'хуакцин'],
        'Мохир' => ['мох', 'мохир'],
        'Яксин' => ['ясин', 'яксин'],
    ];

    public function forQueue(string $tab, ?string $kind): array
    {
        $pending = $tab === 'pending';
        $query = TransactionDraft::where('status', $tab)
            ->when($kind, fn ($query) => $query->where('kind', $kind));

        $sentAt = "json_extract(source, '$.sent_at')";
        // SQLite requires a colon in numeric offsets. Drafts can also store +0500.
        $normalized = "case when substr($sentAt, -5, 1) in ('+', '-')
            then substr($sentAt, 1, length($sentAt) - 2) || ':' || substr($sentAt, -2)
            else $sentAt end";
        $messages = (clone $query)->select('chat_key', 'message_key')
            ->selectRaw("coalesce(min(julianday($normalized)), julianday(min(created_at))) as queue_date")
            ->selectRaw('min(created_at) as first_created_at')
            ->groupBy('chat_key', 'message_key')
            ->orderBy('queue_date')
            ->orderBy('first_created_at')
            ->orderBy('chat_key')
            ->orderBy('message_key')
            ->paginate(10)
            ->appends(array_filter(['tab' => $tab, 'kind' => $kind]));

        $drafts = $messages->isEmpty() ? collect() : $query
            ->where(function (Builder $query) use ($messages) {
                foreach ($messages as $message) {
                    $query->orWhere(['chat_key' => $message->chat_key, 'message_key' => $message->message_key]);
                }
            })
            ->with('images:id,draft_id,locator_index')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $byMessage = $drafts->groupBy(fn ($draft) => $draft->chat_key.'|'.$draft->message_key);
        $messages->setCollection($messages->getCollection()->filter(fn ($message) => $byMessage->has($message->chat_key.'|'.$message->message_key)
        ));
        $catalog = $this->catalog($drafts);
        $fields = $pending
            ? $drafts->pluck('kind')->unique()->mapWithKeys(fn ($kind) => [$kind => $this->fields($kind)])
            : collect();

        return [
            'messages' => $messages->through(fn ($message) => $this->card(
                $byMessage[$message->chat_key.'|'.$message->message_key], $catalog
            )),
            'fields' => $fields,
            'choices' => $fields->map(fn ($definitions) => collect($definitions)
                ->map(fn ($definition) => $definition[1] ?? null)
                ->filter(fn ($options) => $options !== null)),
            'pendingCount' => TransactionDraft::where('status', 'pending')->count(),
            'catalog' => $catalog,
            'cementReview' => $this->cementReview($fields->get('distribution', [])),
        ];
    }

    private function card(Collection $drafts, array $catalog): array
    {
        $byKind = $drafts->groupBy('kind');
        $distributions = $byKind->get('distribution', collect());
        $vehiclePayments = $drafts->filter(fn ($draft) => $draft->kind !== 'distribution'
            && ($draft->current_fields['type'] ?? null) === 'payment'
            && ($draft->current_fields['payment_purpose'] ?? null) === PaymentPurpose::Vehicle->value);
        $freight = $distributions->count() === 1 && $vehiclePayments->count() === 1 ? $vehiclePayments->first() : null;

        return [
            'source' => $this->source($drafts),
            'drafts' => $byKind,
            'values' => $drafts->mapWithKeys(fn ($draft) => [$draft->id => $this->displayValues($draft, $catalog)]),
            'showShop' => $distributions->contains(fn ($draft) => filled($draft->current_fields['shop_id'] ?? null)),
            'freightId' => $freight?->id,
            'review' => [
                'savedDrafts' => $drafts->pluck('current_fields', 'id')->map(fn ($fields) => (object) $fields),
                'distributionIds' => $distributions->pluck('id')->values(),
                'paymentIds' => $drafts->where('kind', '!=', 'distribution')->pluck('id')->values(),
                'cementFamilies' => $distributions->mapWithKeys(fn ($draft) => [
                    $draft->id => $this->sourceCementFamily($draft->source),
                ]),
            ],
        ];
    }

    private function sourceCementFamily(array $source): ?string
    {
        $texts = array_column([$source, ...($source['additional_locators'] ?? [])], 'message_text');
        preg_match_all('/[\p{L}\p{M}]+/u', mb_strtolower(implode("\n", $texts)), $words);
        $families = array_filter(self::CEMENT_FAMILIES, fn ($aliases) => array_intersect($aliases, $words[0]) !== []);

        return count($families) === 1 ? array_key_first($families) : null;
    }

    private function cementReview(array $fields): array
    {
        $categories = collect($fields['product_category_id'][1] ?? [])->pluck('id', 'name');
        $baggedCategory = $categories->get('Цемент');
        $products = collect($fields['product_id'][1] ?? [])->where('product_category_id', $baggedCategory);
        $pairs = [];

        foreach (array_keys(self::CEMENT_FAMILIES) as $family) {
            $pair = [];
            foreach (['400', '500'] as $grade) {
                $matches = $products->filter(fn ($product) => preg_match(
                    "/^$family\\s+[MМ]\\s*$grade$/iu", trim($product['name'])
                ));
                if ($matches->count() === 1) {
                    $pair['M'.$grade] = $matches->first()['id'];
                }
            }
            if (count($pair) === 2) {
                $pairs[$family] = $pair;
            }
        }

        return [
            'baggedCategory' => $baggedCategory,
            'bulkCategory' => $categories->get('Рассыпной'),
            'gradePairs' => (object) $pairs,
        ];
    }

    private function source(Collection $drafts): array
    {
        $primary = [];
        $related = [];
        foreach ($drafts as $draft) {
            $images = $draft->images->keyBy('locator_index');
            $source = $draft->source;
            $primary = $this->mergeLocator($primary, $source, $images->get(0));

            foreach ($source['additional_locators'] ?? [] as $index => $locator) {
                $key = $locator['message_key'];
                $related[$key] = $this->mergeLocator($related[$key] ?? [], $locator, $images->get($index + 1));
            }
        }

        return ['primary' => $primary, 'related' => $related];
    }

    private function mergeLocator(array $message, array $locator, ?TransactionDraftImage $image): array
    {
        $message += $locator;
        $message['message_text'] ??= $locator['message_text'] ?? null;
        $message['image'] ??= $image;

        return $message;
    }

    private function catalog(Collection $drafts): array
    {
        $catalog = [];
        foreach ([
            'client_id' => [Client::withTrashed(), 'name'],
            'shop_id' => [Shop::query(), 'name'],
            'product_id' => [Product::withTrashed(), 'name'],
            'supplier_id' => [Supplier::withTrashed(), 'car_number'],
            'provider_id' => [Provider::withTrashed(), 'name'],
        ] as $field => [$query, $label]) {
            $ids = $drafts->pluck("current_fields.$field")->filter()->unique();
            $catalog[$field] = $ids->isEmpty() ? [] : $query->whereKey($ids->all())->pluck($label, 'id')->all();
        }

        return $catalog;
    }

    private function displayValues(TransactionDraft $draft, array $catalog): array
    {
        $fields = $draft->current_fields;
        $name = fn ($field) => isset($fields[$field]) ? ($catalog[$field][$fields[$field]] ?? null) : null;
        $number = fn ($value) => is_numeric($value)
            ? rtrim(rtrim(number_format((float) $value, 4), '0'), '.')
            : null;

        if ($draft->kind === 'distribution') {
            $unit = isset($fields['quantity_unit']) ? __($fields['quantity_unit']) : null;
            $quantity = $number($fields['quantity'] ?? null);
            $price = $number($fields['price'] ?? null);

            return [
                'client_id' => $name('client_id'),
                'shop_id' => $name('shop_id'),
                'product_id' => $name('product_id'),
                'quantity' => $quantity === null ? null : trim("$quantity $unit"),
                'price' => $price,
                'total' => $quantity === null || $price === null ? null
                    : $number((float) $fields['quantity'] * (float) $fields['price']),
                'supplier_id' => $name('supplier_id'),
            ];
        }

        $amount = $number($fields['amount'] ?? null);
        $currency = $fields['currency'] ?? null;
        $method = PaymentMethod::tryFrom($fields['payment_method'] ?? '');
        $purpose = PaymentPurpose::tryFrom($fields['payment_purpose'] ?? '');
        $notes = $fields['notes'] ?? null;
        $payer = $fields['payer_name'] ?? null;

        return [
            'client_id' => $name('client_id'),
            'provider_id' => $name('provider_id'),
            'type' => isset($fields['type']) ? __($fields['type']) : null,
            'amount' => $amount === null ? null : trim("$amount $currency"),
            'payment_method' => $method ? __($method->label()) : null,
            'description' => filled($notes) ? $notes : $purpose?->label(filled($payer) ? $payer : '—'),
        ];
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
                'product_id' => [__('Product'), Product::orderBy('name')->get(['id', 'name', 'product_category_id', 'default_provider_id', 'buy_price'])->toArray()],
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
