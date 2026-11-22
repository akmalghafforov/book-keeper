<?php

namespace Tests\Feature\Api;

use App\Models\Provider;
use App\Models\TransactionDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TransactionDraftConcurrencyTest extends TestCase
{
    public function test_competing_approval_workers_post_once_against_file_backed_sqlite(): void
    {
        $directory = sys_get_temp_dir().'/draft-approval-'.Str::uuid();
        File::makeDirectory($directory, 0700);
        touch($directory.'/ledger.sqlite');
        $original = config('database.connections.sqlite');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $directory.'/ledger.sqlite',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
        $workers = [];
        try {
            $this->artisan('migrate')->assertSuccessful();
            $user = User::factory()->create();
            $secondReviewer = User::factory()->create();
            $this->artisan('drafts:configure-access', ['--reviewer' => [$user->email, $secondReviewer->email]])->assertSuccessful();
            $provider = Provider::factory()->create();
            $fields = ['provider_id' => $provider->id, 'type' => 'charge', 'amount' => '12.00', 'provider_received_at' => '2026-11-19T09:10:00+05:00'];
            $source = ['chat_key' => 'test', 'message_key' => 'fingerprint', 'event_slot' => 'leg', 'export_id' => 'test', 'line' => 1, 'sent_at' => $fields['provider_received_at']];
            $draft = TransactionDraft::create([
                'kind' => 'provider_ledger', 'submitted_by' => $user->id, 'source' => $source,
                'original_fields' => $fields, 'current_fields' => $fields,
                ...array_intersect_key($source, array_flip(['chat_key', 'message_key', 'event_slot'])),
            ]);

            foreach (['first' => $user, 'second' => $secondReviewer] as $worker => $reviewer) {
                $process = new Process([
                    PHP_BINARY, base_path('tests/Support/transaction-draft-worker.php'), $directory, $worker, (string) $reviewer->id, $draft->id,
                ], base_path(), ['APP_ENV' => 'testing'], timeout: 15);
                $process->start();
                $workers[] = $process;
            }

            [$first, $second] = array_map(function (Process $worker): array {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());

                $this->assertJson($worker->getOutput(), $worker->getOutput().$worker->getErrorOutput());

                return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }, $workers);
            $this->assertSame(200, $first['status'], $first['body']);
            $this->assertSame(409, $second['status'], $second['body']);
            $this->assertDatabaseCount('provider_ledgers', 1);
            $this->assertDatabaseCount('transaction_draft_events', 1);
            $this->assertDatabaseHas('transaction_draft_events', ['draft_id' => $draft->id, 'type' => 'approved', 'actor_id' => $user->id]);
        } finally {
            foreach ($workers as $worker) {
                $worker->stop();
            }
            DB::purge('sqlite');
            config(['database.connections.sqlite' => $original]);
            File::deleteDirectory($directory);
        }
    }
}
