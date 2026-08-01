<?php

namespace App\Data;

final class ProviderLedgerReconciliationResult
{
    public function __construct(
        public readonly int $matchedDeliveries,
        public readonly int $matchedPayments,
        public readonly array $missingDeliveries,
        public readonly array $missingPayments,
        public readonly array $extraDeliveries,
        public readonly array $extraPayments,
        public readonly array $invalidEntries,
    ) {}

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
}
