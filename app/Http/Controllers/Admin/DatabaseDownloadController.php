<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseDownloadController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $connection = DB::connection();

        abort_unless($connection->getDriverName() === 'sqlite', 404);

        $databasePath = $connection->getDatabaseName();

        abort_unless(is_string($databasePath) && is_file($databasePath), 404);

        return response()->download(
            $databasePath,
            'taqsimot_db_'.now(config('app.timezone'))->format('Ymd').'.sqlite',
            ['Content-Type' => 'application/vnd.sqlite3'],
        );
    }
}
