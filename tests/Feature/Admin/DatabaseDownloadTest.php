<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DatabaseDownloadController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
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
        file_put_contents($databasePath, 'SQLite format 3');
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn($databasePath);
        DB::shouldReceive('connection')->once()->andReturn($connection);

        try {
            $response = app(DatabaseDownloadController::class)();

            $this->assertStringContainsString(
                'taqsimot_db_20260905.sqlite',
                $response->headers->get('content-disposition', ''),
            );
        } finally {
            Carbon::setTestNow();
            unlink($databasePath);
        }
    }
}
