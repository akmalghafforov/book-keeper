<?php

namespace Tests\Feature\Admin;

use App\Models\User;
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
        $user = User::factory()->create();
        copy($this->databasePath, $this->sourcePath);
        $source = new \PDO('sqlite:'.$this->sourcePath);
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
    }

    public function test_invalid_database_does_not_replace_the_active_database(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('admin.debt-ledgers.index'))
            ->post(route('admin.database.import'), [
                'database' => UploadedFile::fake()->createWithContent('broken.sqlite', 'not a database'),
            ])
            ->assertRedirect(route('admin.debt-ledgers.index'))
            ->assertSessionHasErrors('database');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDirectoryDoesNotExist($this->databaseDirectory.'/backups');
    }

}
