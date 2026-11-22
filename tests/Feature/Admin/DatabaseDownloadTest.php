<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DatabaseDownloadController;
use App\Services\SqliteDatabaseSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use Tests\TestCase;

class DatabaseDownloadTest extends TestCase
{
    public function test_database_download_requires_authentication(): void
    {
        $this->get(route('admin.database.download'))
            ->assertRedirect(route('login'));
    }

    public function test_sqlite_database_download_uses_a_dated_filename(): void
    {
        Carbon::setTestNow('2026-09-05 10:00:00');
        $databasePath = tempnam(sys_get_temp_dir(), 'taqsimot-db-');
        $database = new PDO('sqlite:'.$databasePath);
        $database->exec('PRAGMA journal_mode = WAL');
        $database->exec('CREATE TABLE transaction_drafts (id TEXT PRIMARY KEY)');
        $database->exec("INSERT INTO transaction_drafts VALUES ('draft-in-wal')");
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn($databasePath);
        DB::shouldReceive('connection')->once()->andReturn($connection);

        try {
            $response = app(DatabaseDownloadController::class)(app(SqliteDatabaseSnapshot::class));

            $this->assertStringContainsString(
                'taqsimot_db_20260905.sqlite',
                $response->headers->get('content-disposition', ''),
            );
            $download = $response->getFile()->getPathname();
            $snapshot = new PDO('sqlite:'.$download);
            $this->assertSame('draft-in-wal', $snapshot->query('SELECT id FROM transaction_drafts')->fetchColumn());
        } finally {
            Carbon::setTestNow();
            unset($database, $snapshot);
            if (isset($download)) {
                unlink($download);
            }
            unlink($databasePath);
        }
    }
}
