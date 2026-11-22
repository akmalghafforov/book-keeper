<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use DateTimeInterface;
use Illuminate\Validation\Rule;

class ManualProviderLedgerEntry
{
    public static function rules(?PaymentCurrencyConverter $converter = null): array
    {
        return [
            'provider_id' => 'required|exists:providers,id',
            'type' => ['required', Rule::in(['charge', 'payment'])],
            'payment_method' => ['nullable', 'required_if:type,payment', Rule::enum(PaymentMethod::class)],
            'payment_purpose' => ['exclude_unless:type,payment', 'nullable', Rule::enum(PaymentPurpose::class), Rule::notIn([PaymentPurpose::Other->value])],
            'payer_name' => ['exclude_unless:payment_purpose,on_behalf_of', 'required', 'string', 'max:255'],
            'amount' => 'required|numeric|min:0.01',
            ...($converter?->rules() ?? []),
            'car_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public const OFFSET_TIMESTAMP = [
        'string',
        'date',
        'regex:/\A\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,6})?)?(?:Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)\z/',
    ];

    public static function attributes(array $validated, DateTimeInterface $receivedAt): array
    {
        $isPayment = $validated['type'] === 'payment';
        $paymentPurpose = $isPayment ? ($validated['payment_purpose'] ?? null) : null;

        return [
            'provider_id' => $validated['provider_id'],
            'type' => $validated['type'],
            'payment_method' => $isPayment ? $validated['payment_method'] : null,
            'payment_purpose' => $paymentPurpose,
            'payer_name' => $paymentPurpose === PaymentPurpose::OnBehalfOf->value ? $validated['payer_name'] : null,
            'distribution_id' => null,
            'product_id' => null,
            'quantity' => null,
            'buy_price' => null,
            'amount' => $validated['amount'],
            'car_number' => $isPayment ? null : ($validated['car_number'] ?? null),
            'transaction_date' => $receivedAt->format('Y-m-d'),
            'provider_received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
