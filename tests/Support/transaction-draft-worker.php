<?php

use App\Models\ProviderLedger;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

[$directory, $worker, $actor, $draftId] = array_slice($argv, 1);
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $directory.'/ledger.sqlite',
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.busy_timeout' => 5000,
    'session.driver' => 'array',
]);

$waitFor = static function (string $signal) use ($directory): void {
    $deadline = microtime(true) + 6;
    while (! is_file($directory.'/'.$signal)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out waiting for '.$signal);
        }
        usleep(5000);
    }
};

DB::connection()->beforeExecuting(function (string $sql) use ($worker, $directory, $waitFor): void {
    static $ready = false;
    if ($ready || ! str_starts_with($sql, 'update "transaction_drafts"')) {
        return;
    }
    $ready = true;
    if ($worker === 'first') {
        $waitFor('second-ready');
    } else {
        touch($directory.'/second-ready');
        $waitFor('first-writing');
        touch($directory.'/second-writing');
    }
});

if ($worker === 'first') {
    ProviderLedger::creating(static function () use ($directory, $waitFor): void {
        touch($directory.'/first-writing');
        $waitFor('second-writing');
        usleep(100_000);
    });
}

$request = Request::create('/admin/transaction-drafts/'.$draftId.'/approve', 'POST', server: [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_IF_MATCH' => '"1"',
    'HTTP_IDEMPOTENCY_KEY' => 'approval-'.$worker,
], content: '{}');
$app->instance('request', $request);
Auth::guard('web')->setUser(User::findOrFail($actor));
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent()], JSON_THROW_ON_ERROR);
