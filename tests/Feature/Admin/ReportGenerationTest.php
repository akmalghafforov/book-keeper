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

        $response = $this->actingAs($this->user)->post(route('admin.reports.export-client-debt', $client), [
            'format' => 'png',
        ]);

        $response->assertRedirect(route('admin.reports.index'));

        $report = GeneratedReport::query()->firstOrFail();

        $this->assertSame($report->id, $report->serial_number);
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
}
