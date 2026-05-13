<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateClientDebtReport;
use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\ClientDebtReportDataBuilder;
use App\Services\GeneratedReportLedgerBoundaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_export_client_debt_assigns_a_serial_number_to_the_report(): void
    {
        Bus::fake();

        $client = Client::factory()->create();
        $ledger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('admin.reports.export-client-debt', $client), [
            'format' => 'png',
        ]);

        $response->assertRedirect(route('admin.reports.index'));

        $report = GeneratedReport::query()->firstOrFail();

        $this->assertSame($report->id, $report->serial_number);
        $this->assertSame($ledger->id, $report->last_included_ledger_id);
        $this->assertNotEmpty($report->parameters['cutoff_at']);
        Bus::assertDispatched(GenerateClientDebtReport::class);
    }

    public function test_builder_aggregates_previously_reported_transactions_and_only_lists_new_ledgers(): void
    {
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        $firstCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        $payment = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 30,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow('2026-04-03 09:00:00');
        $secondCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 20,
            'transaction_date' => '2026-04-03',
        ]);

        Carbon::setTestNow('2026-04-03 10:00:00');
        $previousReport = GeneratedReport::create([
            'name' => 'Debt Report: Test Client (2026-04-03 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
        ]);

        $previousReport->forceFill([
            'last_included_ledger_id' => $secondCharge->id,
        ])->saveQuietly();

        Carbon::setTestNow('2026-04-04 09:00:00');
        $newCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 50,
            'transaction_date' => '2026-04-04',
        ]);

        Carbon::setTestNow('2026-04-05 09:00:00');
        $newPayment = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 10,
            'transaction_date' => '2026-04-05',
        ]);

        Carbon::setTestNow('2026-04-05 10:00:00');
        $currentReport = GeneratedReport::create([
            'name' => 'Debt Report: Test Client (2026-04-05 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'pending',
        ]);

        Carbon::setTestNow();

        $payload = app(ClientDebtReportDataBuilder::class)->build($currentReport);

        $this->assertTrue($payload->has_previously_reported_transactions);
        $this->assertSame(1, $payload->previous_report_count);
        $this->assertEquals(90.0, (float) $payload->previously_reported_total);
        $this->assertSame([$newCharge->id, $newPayment->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertSame([140.0, 130.0], $payload->recentLedgers->pluck('running_balance')->map(fn ($value) => (float) $value)->all());
        $this->assertSame($newPayment->id, $payload->last_included_ledger_id);
        $this->assertEquals(130.0, (float) $payload->calculated_total_debt);
        $this->assertSame($secondCharge->id, $payload->reported_through_ledger_id);
    }

    public function test_builder_uses_report_completion_time_as_a_legacy_cutoff_when_no_cutoff_is_stored(): void
    {
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 80,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 20,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow('2026-04-03 10:00:00');
        GeneratedReport::create([
            'name' => 'Debt Report: Legacy Client (2026-04-03 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
        ]);

        Carbon::setTestNow('2026-04-04 09:00:00');
        $newCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 25,
            'transaction_date' => '2026-04-04',
        ]);

        Carbon::setTestNow('2026-04-04 10:00:00');
        $currentReport = GeneratedReport::create([
            'name' => 'Debt Report: Legacy Client (2026-04-04 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'pending',
        ]);

        Carbon::setTestNow();

        $payload = app(ClientDebtReportDataBuilder::class)->build($currentReport);

        $this->assertTrue($payload->has_previously_reported_transactions);
        $this->assertEquals(60.0, (float) $payload->previously_reported_total);
        $this->assertSame([$newCharge->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertSame($newCharge->id, $payload->last_included_ledger_id);
    }

    public function test_backfill_sets_last_included_ledger_id_for_existing_completed_reports(): void
    {
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        $lastIncludedLedger = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow('2026-04-03 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'Debt Report: Existing Client (2026-04-03 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
        ]);

        Carbon::setTestNow('2026-04-04 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 55,
            'transaction_date' => '2026-04-04',
        ]);

        Carbon::setTestNow();

        $updatedCount = app(GeneratedReportLedgerBoundaryService::class)->backfillMissingLastIncludedLedgerIds();

        $this->assertSame(1, $updatedCount);
        $this->assertSame($lastIncludedLedger->id, $report->fresh()->last_included_ledger_id);
    }

    public function test_backfill_does_not_override_existing_last_included_ledger_id_values(): void
    {
        $client = Client::factory()->create();

        $ledger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        $report = GeneratedReport::create([
            'name' => 'Debt Report: Existing Client (2026-04-03 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
            'last_included_ledger_id' => $ledger->id,
        ]);

        $updatedCount = app(GeneratedReportLedgerBoundaryService::class)->backfillMissingLastIncludedLedgerIds();

        $this->assertSame(0, $updatedCount);
        $this->assertSame($ledger->id, $report->fresh()->last_included_ledger_id);
    }

    public function test_regenerating_single_client_report_keeps_the_original_report_window(): void
    {
        Bus::fake();

        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        $previousLedger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-01 10:00:00');
        GeneratedReport::create([
            'name' => 'Debt Report: Window Client (2026-04-01 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
            'last_included_ledger_id' => $previousLedger->id,
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        $includedLedger = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 30,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow('2026-04-02 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'Debt Report: Window Client (2026-04-02 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
            'last_included_ledger_id' => $includedLedger->id,
        ]);

        Carbon::setTestNow('2026-04-03 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 200,
            'transaction_date' => '2026-04-03',
        ]);

        Carbon::setTestNow('2026-04-04 10:00:00');
        $response = $this->actingAs($this->user)->post(route('admin.reports.regenerate', $report));

        $response->assertRedirect(route('admin.reports.index'));

        $report->refresh();
        $payload = app(ClientDebtReportDataBuilder::class)->build($report);

        $this->assertSame('pending', $report->status);
        $this->assertSame($includedLedger->id, $report->last_included_ledger_id);
        $this->assertSame([$includedLedger->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertEquals(70.0, (float) $payload->calculated_total_debt);
        $this->assertSame($previousLedger->id, $payload->reported_through_ledger_id);
        Bus::assertDispatched(GenerateClientDebtReport::class);

        Carbon::setTestNow();
    }

    public function test_regenerating_legacy_report_resolves_the_cutoff_before_marking_it_pending(): void
    {
        Bus::fake();

        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        $includedLedger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'Debt Report: Legacy Regeneration Client (2026-04-02 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
        ]);

        Carbon::setTestNow('2026-04-03 09:00:00');
        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-03',
        ]);

        Carbon::setTestNow('2026-04-04 10:00:00');
        $response = $this->actingAs($this->user)->post(route('admin.reports.regenerate', $report));

        $response->assertRedirect(route('admin.reports.index'));

        $report->refresh();
        $payload = app(ClientDebtReportDataBuilder::class)->build($report);

        $this->assertSame('pending', $report->status);
        $this->assertSame($includedLedger->id, $report->last_included_ledger_id);
        $this->assertSame('2026-04-02 10:00:00', $report->parameters['cutoff_at']);
        $this->assertSame([$includedLedger->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertEquals(100.0, (float) $payload->calculated_total_debt);
        Bus::assertDispatched(GenerateClientDebtReport::class);

        Carbon::setTestNow();
    }

    public function test_all_clients_report_builder_uses_the_report_ledger_boundary(): void
    {
        $includedClient = Client::factory()->create(['name' => 'Included Client']);
        $excludedClient = Client::factory()->create(['name' => 'Excluded Client']);

        Carbon::setTestNow('2026-04-01 09:00:00');
        $includedLedger = DebtLedger::factory()->charge()->create([
            'client_id' => $includedClient->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'All Clients Debt Report (2026-04-02 10:00)',
            'type' => 'client_debt',
            'format' => 'png',
            'parameters' => [
                'locale' => 'en',
            ],
            'status' => 'completed',
            'last_included_ledger_id' => $includedLedger->id,
        ]);

        Carbon::setTestNow('2026-04-03 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $excludedClient->id,
            'amount' => 50,
            'transaction_date' => '2026-04-03',
        ]);

        Carbon::setTestNow();

        $clients = app(ClientDebtReportDataBuilder::class)->buildAll($report);

        $this->assertSame([$includedClient->id], $clients->pluck('id')->all());
        $this->assertSame([100.0], $clients->pluck('calculated_total_debt')->map(fn ($value) => (float) $value)->all());
    }

    public function test_export_client_debt_range_stores_selected_dates_and_dispatches_report_job(): void
    {
        Bus::fake();

        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-10 12:00:00');
        $ledger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-09',
        ]);

        $response = $this->actingAs($this->user)->post(route('admin.reports.export-client-debt-range', $client), [
            'format' => 'png',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $response->assertRedirect(route('admin.reports.index'));

        $report = GeneratedReport::query()->firstOrFail();

        $this->assertSame('single_client_debt_range', $report->type);
        $this->assertSame($client->id, $report->parameters['client_id']);
        $this->assertSame('2026-04-01', $report->parameters['range_start_date']);
        $this->assertSame('2026-04-30', $report->parameters['range_end_date']);
        $this->assertSame('2026-04-10 12:00:00', $report->parameters['cutoff_at']);
        $this->assertSame($ledger->id, $report->last_included_ledger_id);
        Bus::assertDispatched(GenerateClientDebtReport::class);

        Carbon::setTestNow();
    }

    public function test_export_client_debt_range_allows_open_ended_date_range(): void
    {
        Bus::fake();

        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-10 12:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-09',
        ]);

        $response = $this->actingAs($this->user)->post(route('admin.reports.export-client-debt-range', $client), [
            'format' => 'png',
            'start_date' => '2026-04-01',
            'end_date' => '',
        ]);

        $response->assertRedirect(route('admin.reports.index'));

        $report = GeneratedReport::query()->firstOrFail();

        $this->assertSame('single_client_debt_range', $report->type);
        $this->assertSame('2026-04-01', $report->parameters['range_start_date']);
        $this->assertNull($report->parameters['range_end_date']);
        $this->assertSame('Debt Range Report: '.$client->name.' (from 2026-04-01)', $report->name);
        Bus::assertDispatched(GenerateClientDebtReport::class);

        Carbon::setTestNow();
    }

    public function test_export_operation_debt_starts_report_from_selected_operation(): void
    {
        Bus::fake();

        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-10 12:00:00');
        $operation = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-09',
        ]);

        $response = $this->actingAs($this->user)->post(route('admin.reports.export-operation-debt', $operation), [
            'format' => 'jpg',
        ]);

        $response->assertRedirect(route('admin.reports.index'));

        $report = GeneratedReport::query()->firstOrFail();

        $this->assertSame('single_client_debt_range', $report->type);
        $this->assertSame('jpg', $report->format);
        $this->assertSame($client->id, $report->parameters['client_id']);
        $this->assertSame('2026-04-09', $report->parameters['range_start_date']);
        $this->assertNull($report->parameters['range_end_date']);
        $this->assertSame($operation->id, $report->parameters['range_start_ledger_id']);
        $this->assertSame($operation->id, $report->last_included_ledger_id);
        Bus::assertDispatched(GenerateClientDebtReport::class);

        Carbon::setTestNow();
    }

    public function test_date_range_report_builder_lists_selected_transactions_and_summarizes_later_transactions(): void
    {
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-03-31 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-03-31',
        ]);

        Carbon::setTestNow('2026-04-01 09:00:00');
        $rangePayment = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-15 09:00:00');
        $rangeCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 70,
            'transaction_date' => '2026-04-15',
        ]);

        Carbon::setTestNow('2026-05-01 09:00:00');
        $laterPayment = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 20,
            'transaction_date' => '2026-05-01',
        ]);

        Carbon::setTestNow('2026-05-02 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'Debt Range Report: Test Client (2026-04-01 - 2026-04-30)',
            'type' => 'single_client_debt_range',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
                'range_start_date' => '2026-04-01',
                'range_end_date' => '2026-04-30',
            ],
            'status' => 'pending',
            'last_included_ledger_id' => $laterPayment->id,
        ]);

        Carbon::setTestNow();

        $payload = app(ClientDebtReportDataBuilder::class)->build($report);

        $this->assertTrue($payload->is_date_range_report);
        $this->assertSame('2026-04-01', $payload->range_start_date->toDateString());
        $this->assertSame('2026-04-30', $payload->range_end_date->toDateString());
        $this->assertSame(1, $payload->opening_balance_transactions_count);
        $this->assertEquals(100.0, (float) $payload->opening_balance_total);
        $this->assertSame([$rangePayment->id, $rangeCharge->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertSame([60.0, 130.0], $payload->recentLedgers->pluck('running_balance')->map(fn ($value) => (float) $value)->all());
        $this->assertSame(1, $payload->later_transactions_count);
        $this->assertEquals(-20.0, (float) $payload->later_transactions_total);
        $this->assertEquals(110.0, (float) $payload->calculated_total_debt);
        $this->assertSame($laterPayment->id, $payload->last_included_ledger_id);
    }

    public function test_open_ended_date_range_report_builder_lists_transactions_from_start_date_forward(): void
    {
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-03-31 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-03-31',
        ]);

        Carbon::setTestNow('2026-04-01 09:00:00');
        $rangePayment = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-05-01 09:00:00');
        $futureCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 70,
            'transaction_date' => '2026-05-01',
        ]);

        Carbon::setTestNow('2026-05-02 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'Debt Range Report: Test Client (from 2026-04-01)',
            'type' => 'single_client_debt_range',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
                'range_start_date' => '2026-04-01',
                'range_end_date' => null,
            ],
            'status' => 'pending',
            'last_included_ledger_id' => $futureCharge->id,
        ]);

        Carbon::setTestNow();

        $payload = app(ClientDebtReportDataBuilder::class)->build($report);

        $this->assertTrue($payload->is_date_range_report);
        $this->assertSame('2026-04-01', $payload->range_start_date->toDateString());
        $this->assertNull($payload->range_end_date);
        $this->assertEquals(100.0, (float) $payload->opening_balance_total);
        $this->assertSame([$rangePayment->id, $futureCharge->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertSame([60.0, 130.0], $payload->recentLedgers->pluck('running_balance')->map(fn ($value) => (float) $value)->all());
        $this->assertSame(0, $payload->later_transactions_count);
        $this->assertFalse($payload->has_later_transactions);
        $this->assertEquals(130.0, (float) $payload->calculated_total_debt);
    }

    public function test_operation_started_date_range_report_uses_selected_ledger_as_exact_start(): void
    {
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        $sameDayOpeningCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-01 10:00:00');
        $selectedPayment = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        $laterCharge = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 70,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow('2026-04-02 10:00:00');
        $report = GeneratedReport::create([
            'name' => 'Debt Report: Test Client (from operation #'.$selectedPayment->id.')',
            'type' => 'single_client_debt_range',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
                'range_start_date' => '2026-04-01',
                'range_end_date' => null,
                'range_start_ledger_id' => $selectedPayment->id,
            ],
            'status' => 'pending',
            'last_included_ledger_id' => $laterCharge->id,
        ]);

        Carbon::setTestNow();

        $payload = app(ClientDebtReportDataBuilder::class)->build($report);

        $this->assertSame(1, $payload->opening_balance_transactions_count);
        $this->assertEquals(100.0, (float) $payload->opening_balance_total);
        $this->assertSame([$selectedPayment->id, $laterCharge->id], $payload->recentLedgers->pluck('id')->all());
        $this->assertSame([60.0, 130.0], $payload->recentLedgers->pluck('running_balance')->map(fn ($value) => (float) $value)->all());
        $this->assertEquals(130.0, (float) $payload->calculated_total_debt);
        $this->assertSame($sameDayOpeningCharge->id, $payload->debtLedgers->first()->id);
    }
}
