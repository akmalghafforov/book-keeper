<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

class SqliteDatabaseImporter
{
    private const IMPORT_CONNECTION = 'database_import';

    /** @var list<string> */
    private const REQUIRED_SOURCE_TABLES = [
        'migrations',
        'users',
        'clients',
        'products',
        'distributions',
        'debt_ledgers',
    ];

    /** @var list<string> */
    private const REQUIRED_CURRENT_TABLES = [
        'migrations',
        'users',
        'sessions',
        'clients',
        'products',
        'product_categories',
        'distributions',
        'debt_ledgers',
        'providers',
        'provider_ledgers',
    ];

    /**
     * @return array{backup_path: string}
     */
    public function import(UploadedFile $upload): array
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            throw new DatabaseImportException(__('Database import is only available for SQLite projects.'));
        }

        $activePath = $connection->getDatabaseName();

        if (! is_string($activePath) || ! is_file($activePath)) {
            throw new DatabaseImportException(__('The active SQLite database could not be found.'));
        }

        $directory = dirname($activePath);
        $stagingPath = tempnam($directory, '.database-import-');

        if ($stagingPath === false) {
            throw new DatabaseImportException(__('Unable to prepare the uploaded database for import.'));
        }

        try {
            if (! File::copy($upload->getRealPath(), $stagingPath)) {
                throw new DatabaseImportException(__('Unable to prepare the uploaded database for import.'));
            }

            $this->validateSqlite($stagingPath);
            $this->validateSourceSchema($stagingPath);
            $this->migrateStagedDatabase($stagingPath);
            $this->validateCurrentSchema($stagingPath);

            $backupPath = $this->backupActiveDatabase($activePath);

            if (! File::move($stagingPath, $activePath)) {
                throw new DatabaseImportException(__('Unable to activate the imported database. Your current database was not replaced.'));
            }

            DB::purge();

            return ['backup_path' => $backupPath];
        } catch (DatabaseImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new DatabaseImportException(__('The uploaded database could not be imported. Your current database was not replaced.'));
        } finally {
            DB::purge(self::IMPORT_CONNECTION);

            if (is_file($stagingPath)) {
                File::delete($stagingPath);
            }
        }
    }

    private function validateSqlite(string $path): void
    {
        $header = file_get_contents($path, false, null, 0, 16);

        if ($header !== "SQLite format 3\000") {
            throw new DatabaseImportException(__('The selected file is not a valid SQLite database.'));
        }

        try {
            $database = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $result = $database->query('PRAGMA integrity_check')->fetchColumn();
        } catch (Throwable) {
            throw new DatabaseImportException(__('The selected SQLite database is corrupted or cannot be opened.'));
        }

        if ($result !== 'ok') {
            throw new DatabaseImportException(__('The selected SQLite database failed its integrity check.'));
        }
    }

    private function validateSourceSchema(string $path): void
    {
        $database = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = $this->tableNames($database);

        if (array_diff(self::REQUIRED_SOURCE_TABLES, $tables) !== []) {
            throw new DatabaseImportException(__('The selected database is not a compatible Taqsimot database.'));
        }

        $knownMigrations = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->all();
        $migrations = $database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        if (array_diff($migrations, $knownMigrations) !== []) {
            throw new DatabaseImportException(__('The selected database was created by an unsupported version of Taqsimot.'));
        }
    }

    private function migrateStagedDatabase(string $path): void
    {
        $configuration = config('database.connections.sqlite');
        $configuration['database'] = $path;
        config(['database.connections.'.self::IMPORT_CONNECTION => $configuration]);
        DB::purge(self::IMPORT_CONNECTION);

        try {
            Artisan::call('migrate', [
                '--database' => self::IMPORT_CONNECTION,
                '--force' => true,
            ]);
        } catch (Throwable) {
            throw new DatabaseImportException(__('The selected database could not be upgraded to the current Taqsimot version.'));
        }
    }

    private function validateCurrentSchema(string $path): void
    {
        $database = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if (array_diff(self::REQUIRED_CURRENT_TABLES, $this->tableNames($database)) !== []) {
            throw new DatabaseImportException(__('The imported database is missing required Taqsimot tables.'));
        }

        if ($database->query('PRAGMA foreign_key_check')->fetch() !== false) {
            throw new DatabaseImportException(__('The imported database contains invalid relationships.'));
        }
    }

    /** @return list<string> */
    private function tableNames(PDO $database): array
    {
        return $database->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    private function backupActiveDatabase(string $activePath): string
    {
        $backupDirectory = dirname($activePath).DIRECTORY_SEPARATOR.'backups';
        File::ensureDirectoryExists($backupDirectory);
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.'taqsimot_db_pre_import_'.now(config('app.timezone'))->format('Ymd_His_u').'.sqlite';

        if (! File::copy($activePath, $backupPath)) {
            throw new DatabaseImportException(__('Unable to back up the current database. The import was cancelled.'));
        }

        return $backupPath;
    }
}
