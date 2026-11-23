<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TransactionDraft;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TransactionDraftMissingFields
{
    public function __construct(private TransactionDraftPosting $posting) {}

    public function validate(TransactionDraft $draft, array $input): array
    {
        $rules = $this->posting->formatRules($draft->kind);
        $shape = Validator::make($input, ['fields' => $rules['fields']])->validate();
        $changes = $shape['fields'];
        foreach ($changes as $field => $value) {
            if (! $this->eligible($draft, $field)) {
                throw ValidationException::withMessages(['fields.'.$field => __('This field already has an original or saved value.')]);
            }
            if ($this->missing($value)) {
                unset($changes[$field]);
            }
        }

        $merged = array_replace($draft->current_fields, $changes);
        if ($draft->kind !== 'distribution' && ($merged['type'] ?? null) === 'payment'
            && ($merged['currency'] ?? null) === 'TJS' && $this->eligible($draft, 'exchange_rate')) {
            $changes['exchange_rate'] = 1;
        }

        if (is_string($changes['provider_received_at'] ?? null)) {
            $timestamp = $changes['provider_received_at'];
            if (Validator::make(['date' => $timestamp], ['date' => 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'])->passes()) {
                $changes['provider_received_at'] = Date::parse($timestamp, config('app.timezone'))->toIso8601String();
            }
        }

        $merged = array_map(fn ($value) => $this->missing($value) ? null : $value, array_replace($draft->current_fields, $changes));
        Validator::make(['fields' => $merged], $rules)->validate();

        foreach (array_keys($changes) as $field) {
            if (! $this->applicable($draft->kind, $field, $merged)) {
                throw ValidationException::withMessages(['fields.'.$field => __('This field does not apply to this transaction.')]);
            }
        }

        $prepared = $this->posting->prepare($draft->kind, $merged);
        if ($prepared['issues']) {
            throw ValidationException::withMessages($prepared['issues']);
        }

        return $changes;
    }

    private function missing(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function eligible(TransactionDraft $draft, string $field): bool
    {
        return $this->missing($draft->original_fields[$field] ?? null) && $this->missing($draft->current_fields[$field] ?? null);
    }

    private function applicable(string $kind, string $field, array $fields): bool
    {
        $payment = ($fields['type'] ?? null) === 'payment';

        return match ($field) {
            'currency', 'exchange_rate', 'payment_method', 'payment_purpose', 'reference_id' => $payment,
            'payer_name' => $payment && ($fields['payment_purpose'] ?? null) === 'on_behalf_of',
            'car_number' => ($fields['type'] ?? null) === 'charge',
            'credit_client_price' => isset($fields['credit_client_id']),
            'provider_buy_price', 'provider_received_at' => $kind !== 'distribution'
                || (isset($fields['product_id']) && Product::whereKey($fields['product_id'])->whereNotNull('default_provider_id')->exists()),
            default => true,
        };
    }
}
