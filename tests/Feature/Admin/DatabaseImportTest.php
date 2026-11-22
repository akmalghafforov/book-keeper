<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\TransactionDraft;
use App\Models\User;
use App\Services\TransactionDraftWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseImportTest extends TestCase
{
    private string $databasePath;

    private string $sourcePath;

    private string $databaseDirectory;

    private string $originalDefaultConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = DB::getDefaultConnection();
        $this->databaseDirectory = sys_get_temp_dir().'/taqsimot-db-import-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->databaseDirectory);
        $this->databasePath = $this->databaseDirectory.'/database.sqlite';
        $this->sourcePath = $this->databaseDirectory.'/source.sqlite';
        File::put($this->databasePath, '');

        $configuration = config('database.connections.sqlite');
        $configuration['database'] = $this->databasePath;
        config(['database.connections.database_import_test' => $configuration]);
        DB::purge('database_import_test');
        DB::setDefaultConnection('database_import_test');
        Artisan::call('migrate', ['--database' => 'database_import_test', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('database_import_test');
        DB::setDefaultConnection($this->originalDefaultConnection);
        config(['database.connections.database_import_test' => null]);

        File::deleteDirectory($this->databaseDirectory);

        parent::tearDown();
    }

    public function test_database_import_requires_authentication(): void
    {
        $this->post(route('admin.database.import'))
            ->assertRedirect(route('login'));
    }

    public function test_valid_database_replaces_active_database_and_creates_a_backup(): void
    {
        $user = $this->operator();
        copy($this->databasePath, $this->sourcePath);
        $source = new \PDO('sqlite:'.$this->sourcePath);
        $source->exec('DROP TABLE transaction_draft_events; DROP TABLE transaction_drafts; ALTER TABLE users DROP COLUMN api_only');
        $source->exec("DELETE FROM api_endpoints WHERE endpoint_key LIKE 'transaction_drafts.%'");
        $source->exec("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code LIKE 'transaction_drafts.%' OR code = 'database.operate')");
        $source->exec("DELETE FROM permissions WHERE code LIKE 'transaction_drafts.%' OR code = 'database.operate'");
        $source->exec("DELETE FROM migrations WHERE migration LIKE '%create_transaction_drafts'");
        $source->exec("INSERT INTO clients (name, created_at, updated_at) VALUES ('Imported client', datetime('now'), datetime('now'))");

        $response = $this->actingAs($user)->post(route('admin.database.import'), [
            'database' => UploadedFile::fake()->createWithContent('backup.sqlite', File::get($this->sourcePath)),
        ]);

        $response->assertRedirect(route('login'))
            ->assertCookie('database_import_status', 'success')
            ->assertCookieExpired(config('session.cookie'));

        DB::purge('database_import_test');
        $this->assertDatabaseHas('clients', ['name' => 'Imported client']);
        $this->assertCount(1, File::files($this->databaseDirectory.'/backups'));
        $this->assertDatabaseCount('transaction_drafts', 0);
        $this->assertDatabaseCount('transaction_draft_events', 0);
    }

    public function test_invalid_database_does_not_replace_the_active_database(): void
    {
        $user = $this->operator();

        $this->actingAs($user)->from(route('admin.debt-ledgers.index'))
            ->post(route('admin.database.import'), [
                'database' => UploadedFile::fake()->createWithContent('broken.sqlite', 'not a database'),
            ])
            ->assertRedirect(route('admin.debt-ledgers.index'))
            ->assertSessionHasErrors('database');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDirectoryDoesNotExist($this->databaseDirectory.'/backups');
    }

    public function test_current_backup_restores_approved_draft_links_and_history(): void
    {
        $operator = $this->operator();
        $this->artisan('drafts:configure-access', ['--reviewer' => [$operator->email]])->assertSuccessful();
        $client = Client::factory()->create();
        $input = ['client_id' => $client->id, 'type' => 'charge', 'amount' => '20.00', 'transaction_date' => '2026-11-19'];
        $source = ['chat_key' => 'test', 'message_key' => 'fingerprint', 'event_slot' => 'charge', 'export_id' => 'test', 'line' => 1, 'sent_at' => '2026-11-19T09:10:00+05:00'];
        $response = DB::transaction(fn () => app(TransactionDraftWorkflow::class)->submit('debt_ledger', $source, $input, $operator->id));
        $id = $response->getData()->data->id;
        $approved = $this->actingAs($operator)->postJson(route('admin.transaction-drafts.approve', $id), [
            'fields' => ['amount' => '21.00'],
        ], ['If-Match' => '"1"', 'Idempotency-Key' => 'approved'])->assertOk();
        $backup = $this->get(route('admin.database.download'))->assertOk()->baseResponse->getFile()->getPathname();
        DB::table('transaction_draft_events')->delete();
        DB::table('transaction_drafts')->delete();
        DB::table('debt_ledgers')->delete();

        try {
            $this->post(route('admin.database.import'), [
                'database' => UploadedFile::fake()->createWithContent('backup.sqlite', File::get($backup)),
            ])->assertRedirect(route('login'))->assertCookie('database_import_status', 'success');
        } finally {
            File::delete($backup);
        }

        $draft = TransactionDraft::findOrFail($id);
        $this->assertSame('approved', $draft->status);
        $this->assertSame($approved->json('data.attributes.posted_record.id'), (string) $draft->posted_record_id);
        $this->assertSame($approved->json('data.attributes.approval'), $draft->approval);
        $this->assertSame(['submitted', 'edited', 'approved'], $draft->events()->pluck('type')->all());
        $this->assertDatabaseHas('debt_ledgers', ['id' => $draft->posted_record_id, 'amount' => 21]);
    }

    private function operator(): User
    {
        $user = User::factory()->create();
        $this->artisan('drafts:configure-access', ['--operator' => [$user->email]])->assertSuccessful();

        return $user->refresh();
    }
}
