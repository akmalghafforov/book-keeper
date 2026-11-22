<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use Illuminate\Validation\Rule;

class ManualDebtLedgerEntry
{
    public static function rules(PaymentCurrencyConverter $converter): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'type' => 'required|in:charge,payment,credit_note',
            'payment_method' => ['nullable', 'required_if:type,payment', Rule::enum(PaymentMethod::class)],
            'payment_purpose' => ['exclude_unless:type,payment', 'nullable', Rule::enum(PaymentPurpose::class)],
            'payer_name' => ['exclude_unless:payment_purpose,on_behalf_of', 'required', 'string', 'max:255'],
            'amount' => 'required|numeric|min:0.01',
            ...$converter->rules(),
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ];
    }

    public static function attributes(array $validated): array
    {
        $payment = $validated['type'] === 'payment';
        $purpose = $payment ? ($validated['payment_purpose'] ?? null) : null;

        return [
            ...$validated,
            'payment_method' => $payment ? $validated['payment_method'] : null,
            'payment_purpose' => $purpose,
            'payer_name' => $purpose === PaymentPurpose::OnBehalfOf->value ? $validated['payer_name'] : null,
        ];
    }
}
