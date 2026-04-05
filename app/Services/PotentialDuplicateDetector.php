<?php

namespace App\Services;

use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\PotentialDuplicateResolution;
use Illuminate\Support\Collection;

class PotentialDuplicateDetector
{
    public const CONTEXT_DEBT_LEDGER = 'debt_ledgers';
    public const CONTEXT_DISTRIBUTION = 'distributions';

    public function detectDebtLedgers(Collection $records, int $limit = 8): Collection
    {
        $groups = $records
            ->groupBy(fn (DebtLedger $ledger) => $this->debtLedgerDuplicateKey($ledger))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group) => $this->buildDebtLedgerGroup($group))
            ->filter();

        return $this->filterResolvedGroups($groups, self::CONTEXT_DEBT_LEDGER)
            ->sortByDesc(fn (array $group) => sprintf(
                '%d-%05d-%010d',
                $this->confidenceWeight($group['confidence']),
                $group['count'],
                $group['latest_timestamp'],
            ))
            ->take($limit)
            ->values();
    }

    public function detectDistributions(Collection $records, int $limit = 8): Collection
    {
        $groups = $records
            ->groupBy(fn (Distribution $distribution) => $this->distributionDuplicateKey($distribution))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group) => $this->buildDistributionGroup($group))
            ->filter();

        return $this->filterResolvedGroups($groups, self::CONTEXT_DISTRIBUTION)
            ->sortByDesc(fn (array $group) => sprintf(
                '%d-%05d-%010d',
                $this->confidenceWeight($group['confidence']),
                $group['count'],
                $group['latest_timestamp'],
            ))
            ->take($limit)
            ->values();
    }

    public function resolveDebtLedgers(Collection $records, ?int $resolvedByUserId = null): bool
    {
        return $this->storeResolution(
            self::CONTEXT_DEBT_LEDGER,
            $this->buildDebtLedgerGroup($records),
            $resolvedByUserId,
        );
    }

    public function resolveDistributions(Collection $records, ?int $resolvedByUserId = null): bool
    {
        return $this->storeResolution(
            self::CONTEXT_DISTRIBUTION,
            $this->buildDistributionGroup($records),
            $resolvedByUserId,
        );
    }

    private function buildDebtLedgerGroup(Collection $group): ?array
    {
        $records = $group
            ->sortByDesc(fn (DebtLedger $ledger) => sprintf(
                '%010d-%010d',
                optional($ledger->transaction_date)?->getTimestamp() ?? 0,
                $ledger->id,
            ))
            ->values();

        if ($records->count() < 2 || $records->map(fn (DebtLedger $ledger) => $this->debtLedgerDuplicateKey($ledger))->unique()->count() !== 1) {
            return null;
        }

        $sameReference = $this->allSameFilledValue($records->pluck('reference_id'));
        $sameNotes = $this->allSameFilledText($records->pluck('notes'));
        $createdClose = $this->createdWithinHours($records, 48);

        $confidence = match (true) {
            $sameReference || ($sameNotes && $createdClose) => 'high',
            $sameNotes || $createdClose => 'medium',
            default => 'low',
        };

        if ($confidence === 'low') {
            return null;
        }

        $reasons = collect(['Same client, type, amount, and transaction date']);

        if ($sameReference) {
            $reasons->push('Matching reference ID');
        }

        if ($sameNotes) {
            $reasons->push('Matching notes');
        }

        if ($createdClose) {
            $reasons->push('Created within 48 hours');
        }

        /** @var DebtLedger $sample */
        $sample = $records->first();

        return [
            'confidence' => $confidence,
            'confidence_label' => ucfirst($confidence) . ' confidence',
            'count' => $records->count(),
            'signature' => $this->debtLedgerSignature($records),
            'record_ids' => $records->pluck('id')->values()->all(),
            'summary' => sprintf(
                '%d similar %s entries for %s on %s',
                $records->count(),
                str_replace('_', ' ', $sample->type),
                $sample->client?->name ?? ('Client #' . $sample->client_id),
                optional($sample->transaction_date)->format('d/m/Y') ?? 'N/A',
            ),
            'reasons' => $reasons->values(),
            'records' => $records,
            'latest_timestamp' => $this->latestTimestamp($records, 'transaction_date'),
        ];
    }

    private function buildDistributionGroup(Collection $group): ?array
    {
        $records = $group
            ->sortByDesc(fn (Distribution $distribution) => sprintf(
                '%010d-%010d',
                optional($distribution->distribution_date)?->getTimestamp() ?? 0,
                $distribution->id,
            ))
            ->values();

        if ($records->count() < 2 || $records->map(fn (Distribution $distribution) => $this->distributionDuplicateKey($distribution))->unique()->count() !== 1) {
            return null;
        }

        $sameSupplier = $this->allSameNullableValue($records->pluck('supplier_id'));
        $sameShop = $this->allSameNullableValue($records->pluck('shop_id'));
        $sameCreditClient = $this->allSameNullableValue($records->pluck('credit_client_id'));
        $createdClose = $this->createdWithinHours($records, 48);

        $matchingDimensions = collect([$sameSupplier, $sameShop, $sameCreditClient])->filter()->count();

        $confidence = match (true) {
            $sameSupplier && $sameShop && $sameCreditClient => 'high',
            $createdClose || $matchingDimensions >= 2 => 'medium',
            default => 'low',
        };

        if ($confidence === 'low') {
            return null;
        }

        $reasons = collect(['Same client, product, unit, quantity, price, and date']);

        if ($sameSupplier) {
            $reasons->push('Matching supplier');
        }

        if ($sameShop) {
            $reasons->push('Matching shop');
        }

        if ($sameCreditClient) {
            $reasons->push('Matching credit client');
        }

        if ($createdClose) {
            $reasons->push('Created within 48 hours');
        }

        /** @var Distribution $sample */
        $sample = $records->first();

        return [
            'confidence' => $confidence,
            'confidence_label' => ucfirst($confidence) . ' confidence',
            'count' => $records->count(),
            'signature' => $this->distributionSignature($records),
            'record_ids' => $records->pluck('id')->values()->all(),
            'summary' => sprintf(
                '%d similar distributions for %s / %s on %s',
                $records->count(),
                $sample->client?->name ?? ('Client #' . $sample->client_id),
                $sample->product?->name ?? ('Product #' . $sample->product_id),
                optional($sample->distribution_date)->format('d/m/Y') ?? 'N/A',
            ),
            'reasons' => $reasons->values(),
            'records' => $records,
            'latest_timestamp' => $this->latestTimestamp($records, 'distribution_date'),
        ];
    }

    private function filterResolvedGroups(Collection $groups, string $context): Collection
    {
        if ($groups->isEmpty()) {
            return $groups;
        }

        $resolvedSignatures = PotentialDuplicateResolution::query()
            ->where('context', $context)
            ->whereIn('signature', $groups->pluck('signature')->all())
            ->pluck('signature');

        if ($resolvedSignatures->isEmpty()) {
            return $groups;
        }

        return $groups->reject(
            fn (array $group) => $resolvedSignatures->contains($group['signature'])
        );
    }

    private function storeResolution(string $context, ?array $group, ?int $resolvedByUserId = null): bool
    {
        if ($group === null) {
            return false;
        }

        PotentialDuplicateResolution::query()->updateOrCreate(
            [
                'context' => $context,
                'signature' => $group['signature'],
            ],
            [
                'resolved_by_user_id' => $resolvedByUserId,
            ],
        );

        return true;
    }

    private function debtLedgerSignature(Collection $records): string
    {
        return $this->buildSignature(
            self::CONTEXT_DEBT_LEDGER,
            $records,
            fn (DebtLedger $ledger) => implode('|', [
                $ledger->id,
                $ledger->client_id,
                $ledger->type,
                $this->normalizeDecimal($ledger->amount, 2),
                optional($ledger->transaction_date)->toDateString() ?? '',
                $ledger->reference_id ?? '',
                $this->normalizeText($ledger->notes),
                optional($ledger->created_at)?->toIso8601String() ?? '',
            ]),
        );
    }

    private function distributionSignature(Collection $records): string
    {
        return $this->buildSignature(
            self::CONTEXT_DISTRIBUTION,
            $records,
            fn (Distribution $distribution) => implode('|', [
                $distribution->id,
                $distribution->client_id,
                $distribution->product_id,
                $distribution->quantity_unit,
                $this->normalizeDecimal($distribution->quantity, 3),
                $this->normalizeDecimal($distribution->price, 4),
                optional($distribution->distribution_date)->toDateString() ?? '',
                $distribution->supplier_id ?? '',
                $distribution->shop_id ?? '',
                $distribution->credit_client_id ?? '',
                optional($distribution->created_at)?->toIso8601String() ?? '',
            ]),
        );
    }

    private function buildSignature(string $context, Collection $records, callable $fingerprint): string
    {
        $payload = $records
            ->map($fingerprint)
            ->sort()
            ->values()
            ->implode('||');

        return hash('sha256', $context . '|' . $payload);
    }

    private function debtLedgerDuplicateKey(DebtLedger $ledger): string
    {
        return implode('|', [
            $ledger->client_id,
            $ledger->type,
            $this->normalizeDecimal($ledger->amount, 2),
            optional($ledger->transaction_date)->toDateString() ?? '',
        ]);
    }

    private function distributionDuplicateKey(Distribution $distribution): string
    {
        return implode('|', [
            $distribution->client_id,
            $distribution->product_id,
            $distribution->quantity_unit,
            $this->normalizeDecimal($distribution->quantity, 3),
            $this->normalizeDecimal($distribution->price, 4),
            optional($distribution->distribution_date)->toDateString() ?? '',
        ]);
    }

    private function latestTimestamp(Collection $records, string $dateField): int
    {
        return (int) $records->max(
            fn ($record) => optional($record->{$dateField})->getTimestamp()
                ?? optional($record->created_at)->getTimestamp()
                ?? 0
        );
    }

    private function createdWithinHours(Collection $records, int $hours): bool
    {
        $createdAts = $records
            ->pluck('created_at')
            ->filter()
            ->sortBy(fn ($date) => $date->getTimestamp())
            ->values();

        if ($createdAts->count() !== $records->count()) {
            return false;
        }

        return $createdAts->first()->diffInHours($createdAts->last()) <= $hours;
    }

    private function allSameFilledValue(Collection $values): bool
    {
        if ($values->contains(fn ($value) => $value === null || $value === '')) {
            return false;
        }

        return $values
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->count() === 1;
    }

    private function allSameNullableValue(Collection $values): bool
    {
        return $values
            ->map(fn ($value) => $value === null || $value === '' ? '__null__' : (string) $value)
            ->unique()
            ->count() === 1;
    }

    private function allSameFilledText(Collection $values): bool
    {
        $normalized = $values
            ->map(fn ($value) => $this->normalizeText($value))
            ->values();

        if ($normalized->contains('')) {
            return false;
        }

        return $normalized->unique()->count() === 1;
    }

    private function normalizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function normalizeDecimal(float|int|string|null $value, int $precision): string
    {
        return number_format((float) $value, $precision, '.', '');
    }

    private function confidenceWeight(string $confidence): int
    {
        return match ($confidence) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }
}
