<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SqliteDatabaseSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseDownloadController extends Controller
{
    public function __invoke(SqliteDatabaseSnapshot $snapshot): BinaryFileResponse
    {
        $connection = DB::connection();
        abort_unless($connection->getDriverName() === 'sqlite', 404);

        $databasePath = $connection->getDatabaseName();
        abort_unless(is_string($databasePath) && is_file($databasePath), 404);

        $download = tempnam(sys_get_temp_dir(), 'taqsimot-download-');
        abort_if($download === false, 500);
        try {
            $snapshot->create($databasePath, $download);
            clearstatcache(true, $download);

            return response()->download(
                $download,
                'taqsimot_db_'.now(config('app.timezone'))->format('Ymd').'.sqlite',
                ['Content-Type' => 'application/vnd.sqlite3'],
            )->deleteFileAfterSend();
        } catch (Throwable $exception) {
            File::delete($download);
            throw $exception;
        }
    }
}
