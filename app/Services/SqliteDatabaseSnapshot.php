<?php

namespace App\Services;

use PDO;

class SqliteDatabaseSnapshot
{
    public function create(string $source, string $destination): void
    {
        // A separate SQLite connection produces a consistent copy, including committed WAL pages.
        $database = new PDO('sqlite:'.$source, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('PRAGMA busy_timeout = 5000');
        $database->prepare('VACUUM INTO ?')->execute([$destination]);
    }
}
