<?php

namespace App\Data;

final class ProviderLedgerReconciliationResult
{
    public readonly int $matchedDeliveries;

    public readonly int $matchedPayments;

    public function __construct(
        public readonly int $excelDeliveryCount,
        public readonly int $ledgerDeliveryCount,
        public readonly int $excelPaymentCount,
        public readonly int $ledgerPaymentCount,
        public readonly string $excelPaymentTotal,
        public readonly string $ledgerPaymentTotal,
        public readonly int $excelPaymentTotalScaled,
        public readonly int $ledgerPaymentTotalScaled,
        public readonly array $exactDeliveryMatches,
        public readonly array $exactPaymentMatches,
        public readonly array $paymentAmountMismatches,
        public readonly array $buyPriceMismatches,
        public readonly array $missingDeliveries,
        public readonly array $missingPayments,
        public readonly array $extraDeliveries,
        public readonly array $extraPayments,
        public readonly array $excelOnlyCarNumbers,
        public readonly array $ledgerOnlyCarNumbers,
        public readonly array $excelOnlyWeights,
        public readonly array $ledgerOnlyWeights,
        public readonly array $excelDuplicateDeliveryGroups,
        public readonly array $ledgerDuplicateDeliveryGroups,
        public readonly array $rankedDeliveryCandidates,
        public readonly array $invalidEntries,
    ) {
        $this->matchedDeliveries = count($this->exactDeliveryMatches);
        $this->matchedPayments = count($this->exactPaymentMatches);
    }

    /** @return array<string, bool> */
    public function checks(): array
    {
        return [
            'payment_count' => $this->excelPaymentCount === $this->ledgerPaymentCount,
            'payment_amounts' => $this->paymentAmountMismatches === []
                && $this->missingPayments === []
                && $this->extraPayments === [],
            'payment_total' => $this->excelPaymentTotalScaled === $this->ledgerPaymentTotalScaled,
            'delivery_count' => $this->excelDeliveryCount === $this->ledgerDeliveryCount,
            'car_numbers' => $this->excelOnlyCarNumbers === [] && $this->ledgerOnlyCarNumbers === [],
            'weights' => $this->excelOnlyWeights === [] && $this->ledgerOnlyWeights === [],
            'duplicates' => $this->excelDuplicateDeliveryGroups === []
                && $this->ledgerDuplicateDeliveryGroups === [],
            'buy_prices' => $this->buyPriceMismatches === [],
            'composite_records' => $this->missingDeliveries === [] && $this->extraDeliveries === [],
        ];
    }

    public function allChecksPass(): bool
    {
        return ! in_array(false, $this->checks(), true) && $this->invalidEntries === [];
    }

    public function matchedCount(): int
    {
        return $this->matchedDeliveries + $this->matchedPayments;
    }

    public function missingCount(): int
    {
        return count($this->missingDeliveries) + count($this->missingPayments);
    }

    public function extraCount(): int
    {
        return count($this->extraDeliveries) + count($this->extraPayments);
    }

    /** @return array<int, string> */
    public function missingCarNumbers(): array
    {
        return $this->excelOnlyCarNumbers;
    }

    /** @return array<int, string> */
    public function extraCarNumbers(): array
    {
        return $this->ledgerOnlyCarNumbers;
    }

    /** @return array<int, string> */
    public function missingWeights(): array
    {
        return $this->excelOnlyWeights;
    }

    /** @return array<int, string> */
    public function extraWeights(): array
    {
        return $this->ledgerOnlyWeights;
    }
}
