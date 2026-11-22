<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProviderLedgerConcurrencyTest extends TestCase
{
    private const ROUTE = '/api/v1/provider-ledgers';

    private string $directory;

    private string $token;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/provider-ledger-'.Str::uuid();
        File::makeDirectory($this->directory, 0700);
        $database = $this->directory.'/ledger.sqlite';
        touch($database);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database,
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());

        $this->provider = Provider::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['code' => 'ledger-test', 'name' => 'Ledger test']);
        $role->permissions()->attach(Permission::where('code', 'like', 'provider_ledgers.%')->pluck('id'));
        $user->roles()->attach($role);
        $this->token = Str::random(32);
        DB::table('personal_access_tokens')->insert(['user_id' => $user->id, 'name' => 'Test', 'token' => hash('sha256', $this->token)]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_concurrent_creates_save_one_entry_and_replay_its_body_and_etag(): void
    {
        $payload = $this->payload('120.00');
        [$first, $second] = $this->compete('POST', self::ROUTE, 'HTTP_IDEMPOTENCY_KEY', 'same-key', $payload, $payload);

        $this->assertSame(201, $first['status'], $first['body']);
        $this->assertNotEmpty($first['etag']);
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('provider_ledgers', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_concurrent_updates_with_the_same_etag_have_one_winner(): void
    {
        $entry = ProviderLedger::factory()->manualCharge()->create(['provider_id' => $this->provider->id]);
        $url = self::ROUTE.'/'.$entry->id;
        $etag = $this->withToken($this->token)->getJson($url)->assertOk()->headers->get('ETag');
        [$first, $second] = $this->compete('PATCH', $url, 'HTTP_IF_MATCH', $etag, $this->payload('120.00'), $this->payload('130.00'));

        $this->assertSame(200, $first['status'], $first['body']);
        $this->assertNotSame($etag, $first['etag']);
        $this->assertSame(412, $second['status'], $second['body']);
        $this->assertSame('120.0000', $entry->fresh()->amount);
    }

    private function compete(string $method, string $url, string $header, string $value, array $first, array $second): array
    {
        $workers = [];
        try {
            foreach (['first' => $first, 'second' => $second] as $worker => $payload) {
                $process = new Process([
                    PHP_BINARY, base_path('tests/Support/provider-ledger-api-worker.php'),
                    $this->directory, $worker, $method, $url, $this->token, $header, $value,
                    json_encode($payload, JSON_THROW_ON_ERROR),
                ], base_path(), ['APP_ENV' => 'testing'], timeout: 15);
                $process->start();
                $workers[] = $process;
            }

            return array_map(function (Process $worker): array {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());

                return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }, $workers);
        } finally {
            foreach ($workers as $worker) {
                $worker->stop();
            }
        }
    }

    private function payload(string $amount): array
    {
        return [
            'provider_id' => (string) $this->provider->id,
            'type' => 'charge',
            'amount' => $amount,
            'provider_received_at' => '2026-06-03T07:00:00+05:00',
        ];
    }
}
