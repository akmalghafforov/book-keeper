<?php

namespace Tests\Feature\Admin;

use App\Data\ProviderLedgerReconciliationResult;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProviderLedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Provider $provider;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->provider = Provider::factory()->create(['name' => 'Excel Provider']);
        $this->withSession(['locale' => 'en']);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_comparison_routes_require_authentication(): void
    {
        $this->get(route('admin.provider-ledgers.compare.form'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.provider-ledgers.compare'))
            ->assertRedirect(route('login'));
    }

    public function test_form_renders_provider_upload_and_date_fields(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.provider-ledgers.compare.form'))
            ->assertOk()
            ->assertSee('Compare Provider Ledger with Excel')
            ->assertSee('Excel Provider')
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false)
            ->assertSee('name="excel_file"', false)
            ->assertSee('The comparison is read-only');
    }

    public function test_comparison_validates_required_fields_provider_dates_and_file(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [])
            ->assertSessionHasErrors(['provider_id', 'date_from', 'date_to', 'excel_file']);

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => 999999,
                'date_from' => '10/06/2026',
                'date_to' => '09/06/2026',
                'excel_file' => UploadedFile::fake()->create('ledger.csv', 1, 'text/csv'),
            ])
            ->assertSessionHasErrors(['provider_id', 'date_to', 'excel_file']);
    }

    public function test_comparison_rejects_files_larger_than_ten_megabytes(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => UploadedFile::fake()->create(
                    'ledger.xlsx',
                    10241,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ])
            ->assertSessionHasErrors('excel_file');
    }

    public function test_it_matches_deliveries_and_payments_with_boundaries_formulas_rounding_and_car_normalization(): void
    {
        $this->createDelivery('2026-06-01', '10.000', '5.1234', '100.0000', 'AB/12.34');
        $this->createPayment('2026-06-01', '50.0000');
        $this->createDelivery('2026-06-05', '3.500', '8.2500', '28.8750', null);
        $this->createPayment('2026-06-10', '75.1250');

        $file = $this->workbook([
            [ExcelDate::PHPToExcel(new \DateTimeImmutable('2026-06-01')), 10.0004, 5.12344, '=50+50.00004', 'ab 12-34', 50.00004],
            ['05.06.2026', 3.5, 8.25, '=B6*C6', '', null],
            ['2026-06-10', null, null, null, null, '=75+0.125'],
            ['31/05/2026', 'not a number', null, null, null, null],
            ['11/06/2026', null, null, null, null, 999],
        ], headerRow: 4, excelDateRows: [5]);

        $before = ProviderLedger::withTrashed()->orderBy('id')->get()->toArray();

        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.compare'), [
            'provider_id' => $this->provider->id,
            'date_from' => '01/06/2026',
            'date_to' => '10/06/2026',
            'excel_file' => $file,
        ]);

        $response
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result): bool {
                return $result->matchedDeliveries === 2
                    && $result->matchedPayments === 2
                    && $result->missingCount() === 0
                    && $result->extraCount() === 0
                    && $result->invalidEntries === [];
            })
            ->assertSee('The Excel file and database ledger match for the selected period.');

        $this->assertSame($before, ProviderLedger::withTrashed()->orderBy('id')->get()->toArray());
    }

    public function test_it_reports_missing_and_extra_entries_consumes_duplicates_and_isolates_provider_and_soft_deletes(): void
    {
        $this->createDelivery('2026-06-03', '2.000', '10.0000', '20.0000', 'AA-1');
        $extra = $this->createPayment('2026-06-04', '99.0000');

        $otherProvider = Provider::factory()->create();
        $this->createPayment('2026-06-05', '40.0000', $otherProvider);
        $deleted = $this->createPayment('2026-06-05', '40.0000');
        $deleted->delete();

        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.compare'), [
            'provider_id' => $this->provider->id,
            'date_from' => '01/06/2026',
            'date_to' => '10/06/2026',
            'excel_file' => $this->workbook([
                ['03/06/2026', 2, 10, 20, 'aa 1', null],
                ['03/06/2026', 2, 10, 20, 'AA-1', null],
                ['05/06/2026', null, null, null, null, 40],
            ]),
        ]);

        $response
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result) use ($extra): bool {
                return $result->matchedDeliveries === 1
                    && $result->matchedPayments === 0
                    && count($result->missingDeliveries) === 1
                    && count($result->missingPayments) === 1
                    && $result->extraDeliveries === []
                    && count($result->extraPayments) === 1
                    && $result->extraPayments[0]['ledger_id'] === $extra->id;
            })
            ->assertSee('Missing in Database')
            ->assertSee('Extra in Database');
    }

    public function test_invalid_in_range_entries_are_reported_while_valid_parts_continue_and_out_of_range_rows_are_ignored(): void
    {
        $this->createPayment('2026-06-02', '25.0000');
        $this->createDelivery('2026-06-03', '4.000', '7.5000', '30.0000', 'X-1');

        $response = $this->actingAs($this->user)->post(route('admin.provider-ledgers.compare'), [
            'provider_id' => $this->provider->id,
            'date_from' => '01/06/2026',
            'date_to' => '10/06/2026',
            'excel_file' => $this->workbook([
                ['not-a-date', 1, 2, 2, 'A-1', null],
                ['02/06/2026', 'bad', null, null, null, 25],
                ['03/06/2026', 4, 7.5, 30, 'x 1', 'bad payment'],
                ['11/06/2026', 'bad', null, null, null, 'bad'],
            ]),
        ]);

        $response
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result): bool {
                return $result->matchedDeliveries === 1
                    && $result->matchedPayments === 1
                    && $result->missingCount() === 0
                    && $result->extraCount() === 0
                    && count($result->invalidEntries) === 3
                    && collect($result->invalidEntries)->pluck('entry_type')->all() === ['row', 'delivery', 'payment'];
            })
            ->assertSee('Invalid Excel Entries')
            ->assertSee('not-a-date');
    }

    public function test_missing_or_malformed_headers_reject_the_workbook(): void
    {
        $file = $this->workbook(
            [['01/06/2026', 1, 2, 2, 'A-1', null]],
            headers: ['Дата', 'Тонна', 'Цена', 'Сумма', 'Номер машины', 'Баланс'],
        );

        $this->actingAs($this->user)
            ->from(route('admin.provider-ledgers.compare.form'))
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $file,
            ])
            ->assertRedirect(route('admin.provider-ledgers.compare.form'))
            ->assertSessionHasErrors('excel_file');
    }

    public function test_database_entries_are_extra_when_the_range_has_no_excel_entries(): void
    {
        $delivery = $this->createDelivery('2026-06-01', '1.000', '2.0000', '2.0000', null);
        $payment = $this->createPayment('2026-06-10', '5.0000');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['31/05/2026', 10, 20, 200, null, null],
                    ['11/06/2026', null, null, null, null, 100],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result) use ($delivery, $payment): bool {
                return $result->matchedCount() === 0
                    && $result->missingCount() === 0
                    && collect($result->extraDeliveries)->pluck('ledger_id')->all() === [$delivery->id]
                    && collect($result->extraPayments)->pluck('ledger_id')->all() === [$payment->id];
            });
    }

    public function test_payments_are_paired_by_date_and_amount_with_deterministic_mismatches_and_scaled_totals(): void
    {
        $this->createPayment('2026-06-02', '10.0000');
        $this->createPayment('2026-06-02', '20.0000');
        $this->createPayment('2026-06-03', '1.2346');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['02/06/2026', 2, 5, 999, 'A-1', 15],
                    ['02/06/2026', null, null, null, null, 15],
                    ['03/06/2026', null, null, null, null, 1.23456],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result): bool {
                return $result->excelPaymentCount === 3
                    && $result->ledgerPaymentCount === 3
                    && $result->excelPaymentTotal === '31.2346'
                    && $result->ledgerPaymentTotal === '31.2346'
                    && $result->matchedPayments === 1
                    && collect($result->paymentAmountMismatches)->map(fn (array $pair): array => [
                        $pair['excel']['amount'],
                        (float) $pair['ledger']['amount'],
                    ])->all() === [[15.0, 10.0], [15.0, 20.0]]
                    && $result->checks()['payment_count']
                    && $result->checks()['payment_total']
                    && ! $result->checks()['payment_amounts'];
            })
            ->assertSee('Payment Mismatches')
            ->assertDontSee('The Excel file and database ledger match for the selected period.');
    }

    public function test_delivery_amount_is_informational_and_buy_price_mismatches_are_paired_by_composite_identity(): void
    {
        $this->createDelivery('2026-06-04', '3.000', '8.5000', '25.5000', 'CAR-7');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['04/06/2026', 3.0004, 8.75, 999999, 'car 7', null],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result): bool {
                return $result->matchedDeliveries === 0
                    && count($result->buyPriceMismatches) === 1
                    && $result->missingDeliveries === []
                    && $result->extraDeliveries === []
                    && ! $result->checks()['buy_prices']
                    && $result->checks()['composite_records'];
            })
            ->assertSee('Buy Price Mismatches');
    }

    public function test_it_reports_bidirectional_sets_and_duplicate_car_date_groups(): void
    {
        $first = $this->createDelivery('2026-06-06', '4.000', '2.0000', '8.0000', 'DUP-1');
        $second = $this->createDelivery('2026-06-06', '5.000', '3.0000', '15.0000', 'dup 1');
        $this->createDelivery('2026-06-07', '9.000', '1.0000', '9.0000', 'LEDGER-ONLY');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['06/06/2026', 4, 2, 8, 'dup 1', null],
                    ['06/06/2026', 6, 4, 24, 'DUP-1', null],
                    ['08/06/2026', 7, 1, 7, 'EXCEL-ONLY', null],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result) use ($first, $second): bool {
                return $result->excelOnlyCarNumbers === ['excelonly']
                    && $result->ledgerOnlyCarNumbers === ['ledgeronly']
                    && $result->excelOnlyWeights === ['6.000', '7.000']
                    && $result->ledgerOnlyWeights === ['5.000', '9.000']
                    && $result->excelDuplicateDeliveryGroups[0]['references'] === [2, 3]
                    && $result->ledgerDuplicateDeliveryGroups[0]['references'] === [$first->id, $second->id]
                    && ! $result->checks()['duplicates'];
            })
            ->assertSee('Excel Duplicate Deliveries')
            ->assertSee('Ledger Duplicate Deliveries')
            ->assertSee('Car and Weight Differences');
    }

    public function test_missing_delivery_candidates_are_ranked_include_consumed_records_and_are_limited_to_five(): void
    {
        $sameCar = $this->createDelivery('2026-06-05', '9.000', '5.0000', '45.0000', 'ABC');
        $previousDate = $this->createDelivery('2026-06-03', '10.000', '5.0000', '50.0000', 'ABC');
        $closeCar = $this->createDelivery('2026-06-05', '10.000', '5.0000', '50.0000', 'ABD');
        $this->createDelivery('2026-06-03', '8.000', '4.0000', '32.0000', 'AX');
        $this->createDelivery('2026-06-02', '7.000', '3.0000', '21.0000', 'AY');
        $this->createDelivery('2026-06-01', '6.000', '2.0000', '12.0000', 'AZ');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['05/06/2026', 10, 5, 50, 'ABC', null],
                    ['03/06/2026', 10, 5, 50, 'ABC', null],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result) use ($sameCar, $closeCar, $previousDate): bool {
                $candidates = $result->missingDeliveries[0]['candidates'];

                return count($candidates) === 5
                    && $result->matchedDeliveries === 1
                    && collect($candidates)->pluck('ledger_id')->take(3)->all() === [
                        $sameCar->id,
                        $closeCar->id,
                        $previousDate->id,
                    ]
                    && $candidates[0]['field_matches'] === [
                        'date' => true,
                        'car_number' => true,
                        'quantity' => false,
                        'price' => true,
                    ];
            })
            ->assertSee('Missing Excel Deliveries and Closest Ledger Candidates')
            ->assertSee('Match')
            ->assertSee('Different');
    }

    public function test_deliveries_match_when_provider_dates_differ_by_one_day_in_either_direction(): void
    {
        $this->createDelivery('2026-06-04', '10.000', '5.0000', '50.0000', 'CAR-1');
        $this->createDelivery('2026-06-07', '12.000', '6.0000', '72.0000', 'CAR-2');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['05/06/2026', 10, 5, 999, 'car 1', null],
                    ['06/06/2026', 12, 6, 999, 'car 2', null],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result): bool {
                return $result->matchedDeliveries === 2
                    && $result->missingDeliveries === []
                    && $result->extraDeliveries === []
                    && $result->buyPriceMismatches === []
                    && $result->allChecksPass();
            })
            ->assertSee('The Excel file and database ledger match for the selected period.');
    }

    public function test_delivery_date_tolerance_does_not_extend_beyond_one_day(): void
    {
        $ledger = $this->createDelivery('2026-06-03', '10.000', '5.0000', '50.0000', 'CAR-1');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['05/06/2026', 10, 5, 50, 'CAR-1', null],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result) use ($ledger): bool {
                return $result->matchedDeliveries === 0
                    && count($result->missingDeliveries) === 1
                    && collect($result->extraDeliveries)->pluck('ledger_id')->all() === [$ledger->id]
                    && $result->missingDeliveries[0]['candidates'][0]['field_matches']['date'] === false;
            });
    }

    public function test_adjacent_delivery_dates_are_paired_for_maximum_matches_and_closest_dates(): void
    {
        $firstLedger = $this->createDelivery('2026-06-04', '10.000', '5.0000', '50.0000', 'CAR-1');
        $secondLedger = $this->createDelivery('2026-06-05', '10.000', '5.0000', '50.0000', 'CAR-1');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['05/06/2026', 10, 5, 50, 'CAR-1', null],
                    ['06/06/2026', 10, 5, 50, 'CAR-1', null],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result) use ($firstLedger, $secondLedger): bool {
                return $result->matchedDeliveries === 2
                    && collect($result->exactDeliveryMatches)->map(fn (array $pair): array => [
                        $pair['excel']['date'],
                        $pair['ledger']['date'],
                        $pair['ledger']['ledger_id'],
                    ])->all() === [
                        ['2026-06-05', '2026-06-04', $firstLedger->id],
                        ['2026-06-06', '2026-06-05', $secondLedger->id],
                    ];
            });
    }

    public function test_buy_price_mismatches_use_delivery_date_tolerance_but_payments_keep_exact_dates(): void
    {
        $this->createDelivery('2026-06-04', '10.000', '5.0000', '50.0000', 'CAR-1');
        $this->createPayment('2026-06-04', '25.0000');

        $this->actingAs($this->user)
            ->post(route('admin.provider-ledgers.compare'), [
                'provider_id' => $this->provider->id,
                'date_from' => '01/06/2026',
                'date_to' => '10/06/2026',
                'excel_file' => $this->workbook([
                    ['05/06/2026', 10, 5.5, 55, 'CAR-1', 25],
                ]),
            ])
            ->assertOk()
            ->assertViewHas('result', function (ProviderLedgerReconciliationResult $result): bool {
                return count($result->buyPriceMismatches) === 1
                    && $result->missingDeliveries === []
                    && $result->extraDeliveries === []
                    && $result->matchedPayments === 0
                    && count($result->missingPayments) === 1
                    && count($result->extraPayments) === 1;
            })
            ->assertSee('04/06/2026');
    }

    private function createDelivery(
        string $date,
        string $quantity,
        string $price,
        string $amount,
        ?string $carNumber,
        ?Provider $provider = null,
    ): ProviderLedger {
        return ProviderLedger::create([
            'provider_id' => ($provider ?? $this->provider)->id,
            'type' => 'charge',
            'quantity' => $quantity,
            'buy_price' => $price,
            'amount' => $amount,
            'car_number' => $carNumber,
            'transaction_date' => $date,
        ]);
    }

    private function createPayment(string $date, string $amount, ?Provider $provider = null): ProviderLedger
    {
        return ProviderLedger::create([
            'provider_id' => ($provider ?? $this->provider)->id,
            'type' => 'payment',
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    private function workbook(
        array $rows,
        int $headerRow = 1,
        array $excelDateRows = [],
        array $headers = ['Дата', 'Тонна', 'Цена', 'Сумма', "Номер\nмашины", 'Сумма оплаты'],
    ): UploadedFile {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        if ($headerRow > 1) {
            $worksheet->setCellValue('A1', 'Provider ledger report');
        }

        $worksheet->fromArray($headers, null, 'A'.$headerRow);

        foreach ($rows as $index => $row) {
            $worksheetRow = $headerRow + $index + 1;
            $worksheet->fromArray($row, null, 'A'.$worksheetRow);
        }

        foreach ($excelDateRows as $row) {
            $worksheet->getStyle('A'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        }

        $path = tempnam(sys_get_temp_dir(), 'provider-ledger-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryFiles[] = $path;

        return new UploadedFile(
            $path,
            'provider-ledger.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
