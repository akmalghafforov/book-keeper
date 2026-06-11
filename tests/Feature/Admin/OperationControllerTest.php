<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\GeneratedReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_index_shows_client_balance_after_each_operation(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 125,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-01 10:00:00');
        DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 30,
            'transaction_date' => '2026-04-01',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 50,
            'transaction_date' => '2026-04-02',
        ]);

        Carbon::setTestNow();

        $response = $this->actingAs($user)->get(route('admin.operations.index'));

        $response->assertOk();
        $response->assertSee(__('Balance'));
        $response->assertSeeInOrder(['145.00', '95.00', '125.00']);
    }

    public function test_operations_index_marks_latest_client_debt_report_records_with_reported_button_when_client_filter_is_selected(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        $previousLedger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
            'notes' => 'Already summarized ledger',
        ]);

        Carbon::setTestNow('2026-04-01 10:00:00');
        GeneratedReport::create([
            'name' => 'Debt Report: Test Client (2026-04-01 10:00)',
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
        $reportedLedger = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-02',
            'notes' => 'Latest report ledger',
        ]);

        Carbon::setTestNow('2026-04-02 10:00:00');
        GeneratedReport::create([
            'name' => 'Debt Report: Test Client (2026-04-02 10:00)',
            'type' => 'single_client_debt',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
            ],
            'status' => 'completed',
            'last_included_ledger_id' => $reportedLedger->id,
        ]);

        Carbon::setTestNow('2026-04-03 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 25,
            'transaction_date' => '2026-04-03',
            'notes' => 'After report ledger',
        ]);

        Carbon::setTestNow();

        $response = $this->actingAs($user)->get(route('admin.operations.index', [
            'client_id' => $client->id,
        ]));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'bg-red-600'));
        $this->assertStringContainsString('bg-red-600', $this->tableRowContaining($response->getContent(), 'Latest report ledger'));
        $this->assertStringContainsString(__('Reported'), $this->tableRowContaining($response->getContent(), 'Latest report ledger'));
        $this->assertStringNotContainsString('bg-red-600', $this->tableRowContaining($response->getContent(), 'Already summarized ledger'));
        $this->assertStringNotContainsString(__('Reported'), $this->tableRowContaining($response->getContent(), 'Already summarized ledger'));
        $this->assertStringNotContainsString('bg-red-600', $this->tableRowContaining($response->getContent(), 'After report ledger'));
        $this->assertStringNotContainsString(__('Reported'), $this->tableRowContaining($response->getContent(), 'After report ledger'));

        $responseWithoutClientFilter = $this->actingAs($user)->get(route('admin.operations.index'));

        $this->assertStringNotContainsString('bg-red-600', $responseWithoutClientFilter->getContent());
        $this->assertStringNotContainsString(__('Reported'), $responseWithoutClientFilter->getContent());
    }

    public function test_operations_index_marks_operation_range_report_records_with_reported_button_when_client_filter_is_selected(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        Carbon::setTestNow('2026-04-01 09:00:00');
        DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 100,
            'transaction_date' => '2026-04-01',
            'notes' => 'Same day opening ledger',
        ]);

        Carbon::setTestNow('2026-04-01 10:00:00');
        $selectedLedger = DebtLedger::factory()->payment()->create([
            'client_id' => $client->id,
            'amount' => 40,
            'transaction_date' => '2026-04-01',
            'notes' => 'Range selected ledger',
        ]);

        Carbon::setTestNow('2026-04-02 09:00:00');
        $laterLedger = DebtLedger::factory()->charge()->create([
            'client_id' => $client->id,
            'amount' => 70,
            'transaction_date' => '2026-04-02',
            'notes' => 'Range later ledger',
        ]);

        Carbon::setTestNow('2026-04-02 10:00:00');
        GeneratedReport::create([
            'name' => 'Debt Report: Test Client (from operation #'.$selectedLedger->id.')',
            'type' => 'single_client_debt_range',
            'format' => 'png',
            'parameters' => [
                'client_id' => $client->id,
                'locale' => 'en',
                'range_start_date' => '2026-04-01',
                'range_end_date' => null,
                'range_start_ledger_id' => $selectedLedger->id,
            ],
            'status' => 'completed',
            'last_included_ledger_id' => $laterLedger->id,
        ]);

        Carbon::setTestNow();

        $response = $this->actingAs($user)->get(route('admin.operations.index', [
            'client_id' => $client->id,
        ]));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'bg-red-600'));
        $this->assertStringContainsString('bg-red-600', $this->tableRowContaining($response->getContent(), 'Range selected ledger'));
        $this->assertStringContainsString(__('Reported'), $this->tableRowContaining($response->getContent(), 'Range selected ledger'));
        $this->assertStringContainsString('bg-red-600', $this->tableRowContaining($response->getContent(), 'Range later ledger'));
        $this->assertStringContainsString(__('Reported'), $this->tableRowContaining($response->getContent(), 'Range later ledger'));
        $this->assertStringNotContainsString('bg-red-600', $this->tableRowContaining($response->getContent(), 'Same day opening ledger'));
        $this->assertStringNotContainsString(__('Reported'), $this->tableRowContaining($response->getContent(), 'Same day opening ledger'));
    }

    private function tableRowContaining(string $content, string $needle): string
    {
        $offset = strpos($content, $needle);

        $this->assertNotFalse($offset, "Unable to find [{$needle}] in response content.");

        $start = strrpos(substr($content, 0, $offset), '<tr');
        $end = strpos($content, '</tr>', $offset);

        $this->assertNotFalse($start, "Unable to find table row start for [{$needle}].");
        $this->assertNotFalse($end, "Unable to find table row end for [{$needle}].");

        return substr($content, $start, $end - $start);
    }
}
