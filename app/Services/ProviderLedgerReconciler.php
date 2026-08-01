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

        [$matchedDeliveries, $missingDeliveries, $extraDeliveries] = $this->matchEntries(
            $deliveries,
            $databaseDeliveries,
            fn (array $entry): string => $this->deliveryKey($entry),
        );
        [$matchedPayments, $missingPayments, $extraPayments] = $this->matchEntries(
            $payments,
            $databasePayments,
            fn (array $entry): string => $this->paymentKey($entry),
        );

        return new ProviderLedgerReconciliationResult(
            matchedDeliveries: $matchedDeliveries,
            matchedPayments: $matchedPayments,
            missingDeliveries: $missingDeliveries,
            missingPayments: $missingPayments,
            extraDeliveries: $extraDeliveries,
            extraPayments: $extraPayments,
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
                    $deliveries[] = [
                        'source_row' => $row,
                        'date' => $dateString,
                        'quantity' => $quantity,
                        'price' => $price,
                        'amount' => $amount,
                        'car_number' => $this->blankToNull($values['car_number']),
                    ];
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
                    $payments[] = [
                        'source_row' => $row,
                        'date' => $dateString,
                        'amount' => $paymentAmount,
                    ];
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

    private function matchEntries(array $excelEntries, array $databaseEntries, callable $key): array
    {
        $databasePools = [];

        foreach ($databaseEntries as $id => $entry) {
            $databasePools[$key($entry)][] = $id;
        }

        $matched = 0;
        $missing = [];

        foreach ($excelEntries as $entry) {
            $entryKey = $key($entry);

            if (! empty($databasePools[$entryKey])) {
                $matchedId = array_shift($databasePools[$entryKey]);
                unset($databaseEntries[$matchedId]);
                $matched++;
            } else {
                $missing[] = $entry;
            }
        }

        return [$matched, $missing, array_values($databaseEntries)];
    }

    private function databaseEntry(ProviderLedger $ledger): array
    {
        return [
            'ledger_id' => $ledger->getKey(),
            'date' => $ledger->transaction_date->toDateString(),
            'quantity' => $ledger->quantity,
            'price' => $ledger->buy_price,
            'amount' => $ledger->amount,
            'car_number' => $ledger->car_number,
        ];
    }

    private function deliveryKey(array $entry): string
    {
        return implode('|', [
            $entry['date'],
            $this->rounded($entry['quantity'], 3),
            $this->rounded($entry['price'], 4),
            $this->rounded($entry['amount'], 4),
            $this->normalizeCarNumber($entry['car_number']),
        ]);
    }

    private function paymentKey(array $entry): string
    {
        return implode('|', [
            $entry['date'],
            $this->rounded($entry['amount'], 4),
        ]);
    }

    private function rounded(mixed $value, int $precision): string
    {
        if ($value === null || ! is_numeric($value)) {
            return '<null>';
        }

        return number_format(round((float) $value, $precision), $precision, '.', '');
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
