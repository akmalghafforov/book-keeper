<?php

namespace App\Services;

use Illuminate\Validation\Rule;

class PaymentCurrencyConverter
{
    public const CURRENCIES = ['TJS', 'USD', 'EUR', 'UZS', 'RUB'];

    /**
     * Rules shared by the two manual ledger payment forms.
     */
    public function rules(): array
    {
        return [
            'currency' => ['exclude_unless:type,payment', 'required', Rule::in(self::CURRENCIES)],
            'exchange_rate' => ['exclude_unless:type,payment', 'required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Converts a validated foreign-currency payment to TJS and records its source.
     */
    public function convert(array $validated): array
    {
        if ($validated['type'] !== 'payment') {
            return $validated;
        }

        // TJS payments have no conversion audit trail. Never trust a submitted TJS rate.
        if ($validated['currency'] === 'TJS') {
            unset($validated['currency'], $validated['exchange_rate']);

            return $validated;
        }

        $amount = (float) $validated['amount'];
        $rate = (float) $validated['exchange_rate'];
        $convertedAmount = round($amount * $rate, 2);
        $audit = sprintf(
            '%s %s × %s = %s TJS',
            $this->formatAmount($amount),
            $validated['currency'],
            $this->formatRate($validated['exchange_rate']),
            $this->formatAmount($convertedAmount),
        );

        $validated['amount'] = $convertedAmount;
        $validated['notes'] = blank($validated['notes'] ?? null)
            ? $audit
            : $validated['notes'].' | '.$audit;
        unset($validated['currency'], $validated['exchange_rate']);

        return $validated;
    }

    private function formatAmount(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function formatRate(mixed $value): string
    {
        return (string) $value;
    }
}
