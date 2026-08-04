<?php

namespace App\Services;

use App\Data\ProviderLedgerReconciliationResult;
use App\Exceptions\InvalidProviderLedgerWorkbook;
use App\Models\Provider;
use App\Models\ProviderLedger;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class ProviderLedgerReconciler
{
    private const REQUIRED_HEADERS = [
        'date' => 'дата',
        'quantity' => 'тонна',
        'price' => 'цена',
        'amount' => 'сумма',
        'car_number' => 'номер машины',
        'payment_amount' => 'сумма оплаты',
    ];

    public function reconcile(
        Provider $provider,
        UploadedFile $file,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): ProviderLedgerReconciliationResult {
        [$deliveries, $payments, $invalidEntries] = $this->readWorkbook($file, $dateFrom, $dateTo);

        $databaseEntries = ProviderLedger::query()
            ->where('provider_id', $provider->getKey())
            ->whereIn('type', ['charge', 'payment'])
            ->whereDate('transaction_date', '>=', $dateFrom->toDateString())
            ->whereDate('transaction_date', '<=', $dateTo->toDateString())
            ->orderBy('id')
            ->get();

        $databaseDeliveries = [];
        $databasePayments = [];

        foreach ($databaseEntries as $ledger) {
            $entry = $this->databaseEntry($ledger);

            if ($ledger->type === 'payment') {
                $databasePayments[$ledger->getKey()] = $entry;
            } else {
                $databaseDeliveries[$ledger->getKey()] = $entry;
            }
        }

        $paymentComparison = $this->matchPayments($payments, $databasePayments);
        $deliveryComparison = $this->matchDeliveries($deliveries, $databaseDeliveries);

        $rankedCandidates = [];
        foreach ($deliveryComparison['missing'] as &$missingDelivery) {
            $missingDelivery['candidates'] = $this->rankDeliveryCandidates($missingDelivery, $databaseDeliveries);
            $rankedCandidates[] = [
                'source_row' => $missingDelivery['source_row'],
                'excel' => $missingDelivery,
                'candidates' => $missingDelivery['candidates'],
            ];
        }
        unset($missingDelivery);

        [$excelOnlyCars, $ledgerOnlyCars] = $this->setDifferences(
            $this->carNumberSet($deliveries),
            $this->carNumberSet($databaseDeliveries),
        );
        [$excelOnlyWeights, $ledgerOnlyWeights] = $this->setDifferences(
            $this->weightSet($deliveries),
            $this->weightSet($databaseDeliveries),
        );

        $excelPaymentTotalScaled = array_sum(array_column($payments, '_amount_scaled'));
        $ledgerPaymentTotalScaled = array_sum(array_column($databasePayments, '_amount_scaled'));

        return new ProviderLedgerReconciliationResult(
            excelDeliveryCount: count($deliveries),
            ledgerDeliveryCount: count($databaseDeliveries),
            excelPaymentCount: count($payments),
            ledgerPaymentCount: count($databasePayments),
            excelPaymentTotal: $this->formatScaled($excelPaymentTotalScaled, 4),
            ledgerPaymentTotal: $this->formatScaled($ledgerPaymentTotalScaled, 4),
            excelPaymentTotalScaled: $excelPaymentTotalScaled,
            ledgerPaymentTotalScaled: $ledgerPaymentTotalScaled,
            exactDeliveryMatches: $deliveryComparison['exact'],
            exactPaymentMatches: $paymentComparison['exact'],
            paymentAmountMismatches: $paymentComparison['mismatches'],
            buyPriceMismatches: $deliveryComparison['mismatches'],
            missingDeliveries: $deliveryComparison['missing'],
            missingPayments: $paymentComparison['missing'],
            extraDeliveries: $deliveryComparison['extra'],
            extraPayments: $paymentComparison['extra'],
            excelOnlyCarNumbers: $excelOnlyCars,
            ledgerOnlyCarNumbers: $ledgerOnlyCars,
            excelOnlyWeights: $excelOnlyWeights,
            ledgerOnlyWeights: $ledgerOnlyWeights,
            excelDuplicateDeliveryGroups: $this->duplicateDeliveryGroups($deliveries, 'source_row'),
            ledgerDuplicateDeliveryGroups: $this->duplicateDeliveryGroups($databaseDeliveries, 'ledger_id'),
            rankedDeliveryCandidates: $rankedCandidates,
            invalidEntries: $invalidEntries,
        );
    }

    private function readWorkbook(UploadedFile $file, Carbon $dateFrom, Carbon $dateTo): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new InvalidProviderLedgerWorkbook('The Excel workbook could not be read.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            throw new InvalidProviderLedgerWorkbook('The Excel workbook could not be read.');
        }

        try {
            $worksheet = $spreadsheet->getSheet(0);
            [$headerRow, $columns] = $this->findHeaders($worksheet);

            return $this->readRows($worksheet, $headerRow, $columns, $dateFrom, $dateTo);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function findHeaders(Worksheet $worksheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $lastHeaderRow = min(50, $worksheet->getHighestDataRow());

        for ($row = 1; $row <= $lastHeaderRow; $row++) {
            $found = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $heading = $this->normalizeHeading($this->calculatedValue($worksheet, $column, $row));
                $field = array_search($heading, self::REQUIRED_HEADERS, true);

                if ($field !== false && ! isset($found[$field])) {
                    $found[$field] = $column;
                }
            }

            if (count($found) === count(self::REQUIRED_HEADERS)) {
                return [$row, $found];
            }
        }

        throw new InvalidProviderLedgerWorkbook('The Excel workbook does not contain the required headers in its first 50 rows.');
    }

    private function readRows(
        Worksheet $worksheet,
        int $headerRow,
        array $columns,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        $deliveries = [];
        $payments = [];
        $invalidEntries = [];

        for ($row = $headerRow + 1; $row <= $worksheet->getHighestDataRow(); $row++) {
            $values = [];

            foreach ($columns as $field => $column) {
                $values[$field] = $this->calculatedValue($worksheet, $column, $row);
            }

            if ($this->allBlank($values)) {
                continue;
            }

            $date = $this->parseDate($worksheet, $columns['date'], $row, $values['date']);

            if ($date === null) {
                $invalidEntries[] = [
                    'row' => $row,
                    'entry_type' => 'row',
                    'date' => null,
                    'date_value' => $this->displayValue($values['date']),
                    'errors' => ['Date is missing or invalid.'],
                ];

                continue;
            }

            if ($date->lt($dateFrom) || $date->gt($dateTo)) {
                continue;
            }

            $dateString = $date->toDateString();

            if (! $this->allBlank([
                $values['quantity'],
                $values['price'],
                $values['amount'],
                $values['car_number'],
            ])) {
                $quantity = $this->parseNumber($values['quantity']);
                $price = $this->parseNumber($values['price']);
                $amount = $this->parseNumber($values['amount']);
                $errors = [];

                if ($quantity === null) {
                    $errors[] = 'Quantity is missing or invalid.';
                }
                if ($price === null) {
                    $errors[] = 'Price is missing or invalid.';
                }
                if ($amount === null) {
                    $errors[] = 'Amount is missing or invalid.';
                }

                if ($errors === []) {
                    $deliveries[] = $this->canonicalizeEntry([
                        'source_row' => $row,
                        'date' => $dateString,
                        'quantity' => $quantity,
                        'price' => $price,
                        'amount' => $amount,
                        'car_number' => $this->blankToNull($values['car_number']),
                    ]);
                } else {
                    $invalidEntries[] = [
                        'row' => $row,
                        'entry_type' => 'delivery',
                        'date' => $dateString,
                        'date_value' => null,
                        'errors' => $errors,
                    ];
                }
            }

            // A nonblank payment cell is always an independent provider-date payment,
            // including when delivery fields are populated on the same Excel row.
            if (! $this->isBlank($values['payment_amount'])) {
                $paymentAmount = $this->parseNumber($values['payment_amount']);

                if ($paymentAmount === null) {
                    $invalidEntries[] = [
                        'row' => $row,
                        'entry_type' => 'payment',
                        'date' => $dateString,
                        'date_value' => null,
                        'errors' => ['Payment amount is invalid.'],
                    ];
                } else {
                    $payments[] = $this->canonicalizeEntry([
                        'source_row' => $row,
                        'date' => $dateString,
                        'amount' => $paymentAmount,
                    ]);
                }
            }
        }

        return [$deliveries, $payments, $invalidEntries];
    }

    private function calculatedValue(Worksheet $worksheet, int $column, int $row): mixed
    {
        $cell = $worksheet->getCell(Coordinate::stringFromColumnIndex($column).$row);

        try {
            return $cell->getCalculatedValue();
        } catch (Throwable) {
            return $cell->getOldCalculatedValue() ?? $cell->getValue();
        }
    }

    private function parseDate(Worksheet $worksheet, int $column, int $row, mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $cell = $worksheet->getCell(Coordinate::stringFromColumnIndex($column).$row);

        if (is_numeric($value) && (ExcelDate::isDateTime($cell) || (float) $value > 0)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        foreach (['d/m/Y', 'd.m.Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return Carbon::instance($date);
            }
        }

        return null;
    }

    private function parseNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[\s\x{00A0}]+/u', '', trim($value));

        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) && is_finite((float) $normalized)
            ? (float) $normalized
            : null;
    }

    private function matchPayments(array $excelEntries, array $ledgerEntries): array
    {
        foreach ($excelEntries as $index => &$entry) {
            $entry['_index'] = $index;
        }
        unset($entry);

        $remainingExcel = $excelEntries;
        $remainingLedger = $ledgerEntries;
        $ledgerPools = [];
        $exact = [];

        foreach ($ledgerEntries as $id => $entry) {
            $ledgerPools[$this->paymentKey($entry)][] = $id;
        }

        foreach ($excelEntries as $index => $entry) {
            $key = $this->paymentKey($entry);
            if (empty($ledgerPools[$key])) {
                continue;
            }

            $id = array_shift($ledgerPools[$key]);
            $exact[] = $this->entryPair($entry, $ledgerEntries[$id]);
            unset($remainingExcel[$index], $remainingLedger[$id]);
        }

        $excelByDate = $this->groupBy($remainingExcel, fn (array $entry): string => $entry['date']);
        $ledgerByDate = $this->groupBy($remainingLedger, fn (array $entry): string => $entry['date']);
        $mismatches = [];

        foreach (array_values(array_unique(array_merge(array_keys($excelByDate), array_keys($ledgerByDate)))) as $date) {
            $excelOnDate = array_values($excelByDate[$date] ?? []);
            $ledgerOnDate = array_values($ledgerByDate[$date] ?? []);
            $this->sortEntries($excelOnDate, '_amount_scaled', 'source_row');
            $this->sortEntries($ledgerOnDate, '_amount_scaled', 'ledger_id');
            $pairCount = min(count($excelOnDate), count($ledgerOnDate));

            for ($i = 0; $i < $pairCount; $i++) {
                $mismatches[] = $this->entryPair($excelOnDate[$i], $ledgerOnDate[$i]);
                unset(
                    $remainingExcel[$excelOnDate[$i]['_index']],
                    $remainingLedger[$ledgerOnDate[$i]['ledger_id']],
                );
            }
        }

        return [
            'exact' => $exact,
            'mismatches' => $mismatches,
            'missing' => array_values($this->withoutInternalIndex($remainingExcel)),
            'extra' => array_values($this->withoutInternalIndex($remainingLedger)),
        ];
    }

    private function matchDeliveries(array $excelEntries, array $ledgerEntries): array
    {
        foreach ($excelEntries as $index => &$entry) {
            $entry['_index'] = $index;
        }
        unset($entry);

        $remainingExcel = $excelEntries;
        $remainingLedger = $ledgerEntries;
        $excelGroups = $this->groupBy($excelEntries, fn (array $entry): string => $this->deliveryIdentityKey($entry));
        $ledgerGroups = $this->groupBy($ledgerEntries, fn (array $entry): string => $this->deliveryIdentityKey($entry));
        $exact = [];
        $mismatches = [];

        foreach (array_values(array_unique(array_merge(array_keys($excelGroups), array_keys($ledgerGroups)))) as $identity) {
            $excelGroup = $excelGroups[$identity] ?? [];
            $ledgerGroup = $ledgerGroups[$identity] ?? [];
            $pricePools = [];

            foreach ($ledgerGroup as $ledgerEntry) {
                $pricePools[$ledgerEntry['_price_scaled']][] = $ledgerEntry['ledger_id'];
            }

            foreach ($excelGroup as $excelEntry) {
                if (empty($pricePools[$excelEntry['_price_scaled']])) {
                    continue;
                }

                $id = array_shift($pricePools[$excelEntry['_price_scaled']]);
                $exact[] = $this->entryPair($excelEntry, $ledgerEntries[$id]);
                unset($remainingExcel[$excelEntry['_index']], $remainingLedger[$id]);
            }

            $unmatchedExcel = array_values(array_filter(
                $excelGroup,
                fn (array $entry): bool => isset($remainingExcel[$entry['_index']]),
            ));
            $unmatchedLedger = array_values(array_filter(
                $ledgerGroup,
                fn (array $entry): bool => isset($remainingLedger[$entry['ledger_id']]),
            ));
            $this->sortEntries($unmatchedExcel, '_price_scaled', 'source_row');
            $this->sortEntries($unmatchedLedger, '_price_scaled', 'ledger_id');
            $pairCount = min(count($unmatchedExcel), count($unmatchedLedger));

            for ($i = 0; $i < $pairCount; $i++) {
                $mismatches[] = $this->entryPair($unmatchedExcel[$i], $unmatchedLedger[$i]);
                unset(
                    $remainingExcel[$unmatchedExcel[$i]['_index']],
                    $remainingLedger[$unmatchedLedger[$i]['ledger_id']],
                );
            }
        }

        return [
            'exact' => $exact,
            'mismatches' => $mismatches,
            'missing' => array_values($this->withoutInternalIndex($remainingExcel)),
            'extra' => array_values($this->withoutInternalIndex($remainingLedger)),
        ];
    }

    private function rankDeliveryCandidates(array $excelEntry, array $ledgerEntries): array
    {
        $ranked = [];

        foreach ($ledgerEntries as $ledgerEntry) {
            $fieldMatches = [
                'date' => $excelEntry['date'] === $ledgerEntry['date'],
                'car_number' => $excelEntry['_car_normalized'] === $ledgerEntry['_car_normalized'],
                'quantity' => $excelEntry['_quantity_scaled'] === $ledgerEntry['_quantity_scaled'],
                'price' => $excelEntry['_price_scaled'] === $ledgerEntry['_price_scaled'],
            ];

            $candidate = $ledgerEntry;
            $candidate['field_matches'] = $fieldMatches;
            $candidate['exact_match_count'] = count(array_filter($fieldMatches));
            $candidate['date_difference'] = abs(Carbon::parse($excelEntry['date'])->diffInDays(Carbon::parse($ledgerEntry['date']), false));
            $candidate['car_edit_distance'] = $this->unicodeEditDistance(
                $excelEntry['_car_normalized'],
                $ledgerEntry['_car_normalized'],
            );
            $candidate['weight_difference_scaled'] = abs($excelEntry['_quantity_scaled'] - $ledgerEntry['_quantity_scaled']);
            $candidate['price_difference_scaled'] = abs($excelEntry['_price_scaled'] - $ledgerEntry['_price_scaled']);
            $ranked[] = $candidate;
        }

        usort($ranked, function (array $left, array $right): int {
            return ($right['exact_match_count'] <=> $left['exact_match_count'])
                ?: ($left['date_difference'] <=> $right['date_difference'])
                ?: ($left['car_edit_distance'] <=> $right['car_edit_distance'])
                ?: ($left['weight_difference_scaled'] <=> $right['weight_difference_scaled'])
                ?: ($left['price_difference_scaled'] <=> $right['price_difference_scaled'])
                ?: ($left['ledger_id'] <=> $right['ledger_id']);
        });

        return array_slice($ranked, 0, 5);
    }

    private function databaseEntry(ProviderLedger $ledger): array
    {
        return $this->canonicalizeEntry([
            'ledger_id' => $ledger->getKey(),
            'date' => $ledger->transaction_date->toDateString(),
            'quantity' => $ledger->quantity,
            'price' => $ledger->buy_price,
            'amount' => $ledger->amount,
            'car_number' => $ledger->car_number,
        ]);
    }

    private function canonicalizeEntry(array $entry): array
    {
        $entry['_amount_scaled'] = $this->toScaled($entry['amount'] ?? null, 4);
        $entry['_quantity_scaled'] = $this->toScaled($entry['quantity'] ?? null, 3);
        $entry['_price_scaled'] = $this->toScaled($entry['price'] ?? null, 4);
        $entry['_car_normalized'] = $this->normalizeCarNumber($entry['car_number'] ?? null);

        return $entry;
    }

    private function deliveryIdentityKey(array $entry): string
    {
        return implode('|', [
            $entry['date'],
            $entry['_car_normalized'],
            $entry['_quantity_scaled'],
        ]);
    }

    private function paymentKey(array $entry): string
    {
        return $entry['date'].'|'.$entry['_amount_scaled'];
    }

    private function duplicateDeliveryGroups(array $entries, string $referenceField): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            if ($entry['_car_normalized'] === '') {
                continue;
            }

            $key = $entry['date'].'|'.$entry['_car_normalized'];
            $groups[$key][] = $entry;
        }

        $duplicates = [];
        foreach ($groups as $entriesInGroup) {
            if (count($entriesInGroup) < 2) {
                continue;
            }

            $duplicates[] = [
                'date' => $entriesInGroup[0]['date'],
                'car_number' => $entriesInGroup[0]['_car_normalized'],
                'references' => array_column($entriesInGroup, $referenceField),
                'entries' => array_values($this->withoutInternalIndex($entriesInGroup)),
            ];
        }

        return $duplicates;
    }

    private function carNumberSet(array $entries): array
    {
        $values = [];
        foreach ($entries as $entry) {
            if ($entry['_car_normalized'] !== '') {
                $values[$entry['_car_normalized']] = true;
            }
        }

        $values = array_keys($values);
        sort($values, SORT_STRING);

        return $values;
    }

    private function weightSet(array $entries): array
    {
        $values = [];
        foreach ($entries as $entry) {
            if ($entry['_quantity_scaled'] !== null) {
                $values[$this->formatScaled($entry['_quantity_scaled'], 3)] = true;
            }
        }

        $values = array_keys($values);
        sort($values, SORT_STRING);

        return $values;
    }

    private function setDifferences(array $excelValues, array $ledgerValues): array
    {
        return [
            array_values(array_diff($excelValues, $ledgerValues)),
            array_values(array_diff($ledgerValues, $excelValues)),
        ];
    }

    private function groupBy(array $entries, callable $key): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$key($entry)][] = $entry;
        }

        return $groups;
    }

    private function sortEntries(array &$entries, string $valueField, string $tieField): void
    {
        usort($entries, fn (array $left, array $right): int => ($left[$valueField] <=> $right[$valueField]) ?: ($left[$tieField] <=> $right[$tieField])
        );
    }

    private function entryPair(array $excelEntry, array $ledgerEntry): array
    {
        unset($excelEntry['_index']);

        return ['excel' => $excelEntry, 'ledger' => $ledgerEntry];
    }

    private function withoutInternalIndex(array $entries): array
    {
        foreach ($entries as &$entry) {
            unset($entry['_index']);
        }
        unset($entry);

        return $entries;
    }

    private function toScaled(mixed $value, int $precision): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value * (10 ** $precision), 0, PHP_ROUND_HALF_UP);
    }

    private function formatScaled(int $value, int $precision): string
    {
        $scale = 10 ** $precision;
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);

        return $sign.intdiv($absolute, $scale).'.'.str_pad((string) ($absolute % $scale), $precision, '0', STR_PAD_LEFT);
    }

    private function unicodeEditDistance(string $left, string $right): int
    {
        $leftChars = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightChars = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $previous = range(0, count($rightChars));

        foreach ($leftChars as $leftIndex => $leftChar) {
            $current = [$leftIndex + 1];
            foreach ($rightChars as $rightIndex => $rightChar) {
                $current[] = min(
                    $current[$rightIndex] + 1,
                    $previous[$rightIndex + 1] + 1,
                    $previous[$rightIndex] + ($leftChar === $rightChar ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($rightChars)];
    }

    private function normalizeCarNumber(mixed $value): string
    {
        if ($this->isBlank($value)) {
            return '';
        }

        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower((string) $value)) ?? '';
    }

    private function normalizeHeading(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            mb_strtolower(trim((string) $value)),
        ) ?? '');
    }

    private function allBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (! $this->isBlank($value)) {
                return false;
            }
        }

        return true;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function blankToNull(mixed $value): ?string
    {
        return $this->isBlank($value) ? null : trim((string) $value);
    }

    private function displayValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
