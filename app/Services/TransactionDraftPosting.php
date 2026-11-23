<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\ProviderLedger;
use App\Models\Supplier;
use App\Models\TransactionDraft;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionDraftPosting
{
    public function __construct(
        private PaymentCurrencyConverter $currency,
        private DistributionService $distributions,
    ) {}

    public function sourceRules(): array
    {
        $opaque = ['string', 'max:128', 'regex:/\A[A-Za-z0-9][A-Za-z0-9_.:-]*\z/'];
        $locatorRules = [
            'message_key' => ['required', ...$opaque],
            'sent_at' => ['required', ...ManualProviderLedgerEntry::OFFSET_TIMESTAMP],
            'export_id' => ['required', ...$opaque],
            'message_text' => ['sometimes', 'required', 'string', 'max:4096'],
        ];
        $rules = [
            'source' => ['required', 'array:chat_key,message_key,sent_at,export_id,line,position,event_slot,additional_locators,message_text'],
            'source.chat_key' => ['required', ...$opaque],
            'source.event_slot' => ['required', ...$opaque],
            'source.line' => ['required_without:source.position', 'integer', 'min:1'],
            'source.position' => ['required_without:source.line', 'integer', 'min:1'],
            'source.additional_locators' => ['sometimes', 'array', 'list', 'max:20'],
            'source.additional_locators.*' => ['required', 'array:message_key,sent_at,export_id,line,position,message_text'],
            'source.additional_locators.*.line' => ['required_without:source.additional_locators.*.position', 'integer', 'min:1'],
            'source.additional_locators.*.position' => ['required_without:source.additional_locators.*.line', 'integer', 'min:1'],
        ];

        foreach ($locatorRules as $field => $rule) {
            $rules['source.'.$field] = $rule;
            $rules['source.additional_locators.*.'.$field] = $rule;
        }

        return $rules;
    }

    public function formatRules(string $kind): array
    {
        $id = ['integer', 'min:1'];
        $number = ['numeric', 'min:0', static function (string $field, mixed $value, Closure $fail): void {
            if (! is_finite((float) $value)) {
                $fail(__('Enter a finite number.'));
            }
        }];
        $unitPrice = [...$number, 'lt:100000000000'];
        $rules = match ($kind) {
            'distribution' => [
                'client_id' => $id,
                'shop_id' => $id,
                'supplier_id' => $id,
                'credit_client_id' => $id,
                'credit_client_price' => $unitPrice,
                'product_category_id' => $id,
                'product_id' => $id,
                'quantity_unit' => [Rule::in(['per_ton', 'per_bag', 'per_piece'])],
                'quantity' => [...$number, 'lt:1000000000000'],
                'price' => $unitPrice,
                'provider_buy_price' => $unitPrice,
                'distribution_date' => ['date_format:Y-m-d'],
                'provider_received_at' => ManualProviderLedgerEntry::OFFSET_TIMESTAMP,
            ],
            'provider_ledger' => [
                'provider_id' => $id,
                'type' => [Rule::in(['charge', 'payment'])],
                'provider_received_at' => ManualProviderLedgerEntry::OFFSET_TIMESTAMP,
                'car_number' => ['string', 'max:50'],
            ],
            'debt_ledger' => [
                'client_id' => $id,
                'type' => [Rule::in(['charge', 'payment', 'credit_note'])],
                'transaction_date' => ['date_format:Y-m-d'],
                'reference_id' => ['integer'],
            ],
        };

        if ($kind !== 'distribution') {
            $purposes = array_column(PaymentPurpose::cases(), 'value');
            $rules += [
                'amount' => $number,
                'currency' => [Rule::in(PaymentCurrencyConverter::CURRENCIES)],
                'exchange_rate' => $number,
                'payment_method' => [Rule::enum(PaymentMethod::class)],
                'payment_purpose' => [Rule::in($kind === 'provider_ledger' ? array_diff($purposes, ['other']) : $purposes)],
                'payer_name' => ['string', 'max:255'],
                'notes' => $kind === 'provider_ledger' ? ['string', 'max:1000'] : ['string'],
            ];
        }

        return [
            'fields' => ['present', 'array:'.implode(',', array_keys($rules))],
            ...Arr::prependKeysWith(array_map(fn (array $rule) => ['bail', 'nullable', ...$rule], $rules), 'fields.'),
        ];
    }

    public function prepare(TransactionDraft $draft, array $fields): array
    {
        $kind = $draft->kind;
        $fields = array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $fields);
        $active = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');
        $rules = match ($kind) {
            'distribution' => [
                ...DistributionService::entryRules(),
                'client_id' => ['required', $active('clients')],
                'shop_id' => ['nullable', Rule::exists('shops', 'id')->where('client_id', $fields['client_id'] ?? null)],
                'supplier_id' => ['nullable', $active('suppliers')],
                'credit_client_id' => ['nullable', $active('clients')],
                'product_id' => ['required', $active('products')->where('product_category_id', $fields['product_category_id'] ?? null)],
                'distribution_date' => ['required'],
            ],
            'provider_ledger' => [
                ...ManualProviderLedgerEntry::rules($this->currency),
                'provider_id' => ['required', $active('providers')],
                'provider_received_at' => ['nullable', 'required_if:type,charge'],
            ],
            'debt_ledger' => [
                ...ManualDebtLedgerEntry::rules($this->currency),
                'client_id' => ['required', $active('clients')],
                'transaction_date' => ['required'],
                'reference_id' => ['nullable', 'prohibited_unless:type,payment'],
            ],
        };

        $validator = Validator::make($fields, $rules);
        if ($validator->fails()) {
            return ['issues' => $validator->errors()->toArray(), 'attributes' => [], 'effects' => []];
        }

        $attributes = $fields;
        $effects = [];
        if ($kind === 'distribution') {
            $product = Product::findOrFail($fields['product_id']);
            unset($attributes['product_category_id']);
            $receivedAt = isset($fields['provider_received_at']) ? $this->localTime($fields['provider_received_at']) : null;
            $attributes['provider_received_at'] = $product->default_provider_id ? $receivedAt?->format('Y-m-d H:i:s') : null;
            $attributes['provider_buy_price'] = $product->default_provider_id ? ($fields['provider_buy_price'] ?? null) : null;
            $distribution = new Distribution($this->distributions->prepare($attributes));
            $effects = $this->distributionEffects($distribution, $product);

            foreach ($effects as $effect) {
                $limit = $effect['values']['type'] === 'credit_note' ? 10000000000000 : 100000000000;
                if ($effect['values']['amount'] >= $limit) {
                    return ['issues' => ['quantity' => [__('The calculated ledger amount is too large.')]], 'attributes' => [], 'effects' => []];
                }
            }
        } else {
            $attributes = $this->currency->convert($validator->validated());
            $limit = $kind === 'provider_ledger' ? 100000000000 : 10000000000000;
            if (! is_finite((float) $attributes['amount']) || $attributes['amount'] >= $limit || $attributes['amount'] < 0.01) {
                return ['issues' => ['amount' => [__('The posted amount is outside the ledger’s supported TJS range.')]], 'attributes' => [], 'effects' => []];
            }
            if ($kind === 'provider_ledger') {
                $receivedAt = isset($fields['provider_received_at']) ? $this->localTime($fields['provider_received_at']) : null;
                $transactionTime = $receivedAt ?? $this->localTime($draft->source['sent_at']);
                $attributes = ManualProviderLedgerEntry::attributes($attributes, $transactionTime);
                $attributes['provider_received_at'] = $receivedAt?->format('Y-m-d H:i:s');
            } else {
                $attributes = ManualDebtLedgerEntry::attributes($attributes);
            }
            $attributes['origin'] = 'manual';
        }

        return ['issues' => [], 'attributes' => $attributes, 'effects' => $effects];
    }

    public function post(string $kind, array $attributes): Model
    {
        return match ($kind) {
            'distribution' => $this->distributions->create($attributes),
            'provider_ledger' => ProviderLedger::create($attributes),
            'debt_ledger' => DebtLedger::create($attributes),
        };
    }

    public function snapshot(string $kind, Model $record, array $reviewed): array
    {
        $record->refresh();
        $generated = [];

        if ($record instanceof Distribution) {
            $debt = DebtLedger::where('reference_id', $record->id)->where('origin', 'distribution')->orderBy('id')->get();
            foreach ($debt as $ledger) {
                $generated[] = $this->recordSnapshot('debt_ledger', $ledger);
            }
            if ($record->providerLedger) {
                $generated[] = $this->recordSnapshot('provider_ledger', $record->providerLedger);
            }
        }

        return [
            'reviewed_fields' => $reviewed,
            'posted_values' => $this->recordSnapshot($kind, $record)['values'],
            'generated_records' => $generated,
        ];
    }

    public function link(string $kind, int|string $id): array
    {
        return ['kind' => $kind, 'id' => (string) $id, 'url' => route(TransactionDraft::ROUTES[$kind], $id, false)];
    }

    private function recordSnapshot(string $kind, Model $record): array
    {
        return [
            ...$this->link($kind, $record->id),
            'values' => Arr::except($record->attributesToArray(), ['created_at', 'updated_at', 'deleted_at']),
        ];
    }

    private function distributionEffects(Distribution $distribution, Product $product): array
    {
        $effects = [['kind' => 'debt_ledger', 'values' => $distribution->customerChargeValues()]];
        if ($distribution->credit_client_id) {
            $effects[] = ['kind' => 'debt_ledger', 'values' => $distribution->creditNoteValues()];
        }
        $supplier = $distribution->supplier_id ? Supplier::find($distribution->supplier_id) : null;
        $providerValues = $distribution->providerChargeValues($product, $supplier?->car_number);
        if ($providerValues !== null) {
            $effects[] = ['kind' => 'provider_ledger', 'values' => $providerValues];
        }

        return $effects;
    }

    private function localTime(string $timestamp): DateTimeInterface
    {
        return Date::parse($timestamp)->setTimezone(config('app.timezone'));
    }
}
