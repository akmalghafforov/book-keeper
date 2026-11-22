<?php

use App\Models\ProviderLedger;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

[$directory, $worker, $method, $url, $token, $header, $value, $body] = array_slice($argv, 1);
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $directory.'/ledger.sqlite',
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.busy_timeout' => 5000,
    'cache.default' => 'array',
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

// Let both requests pass authentication before the first request takes the write lock.
DB::connection()->beforeExecuting(function (string $sql) use ($worker, $directory, $waitFor): void {
    static $ready = false;
    if ($ready || ! preg_match('/^(insert|update)\b.*"(?:idempotency_keys|provider_ledgers)"/i', $sql)) {
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
    $holdLock = static function () use ($directory, $waitFor): void {
        touch($directory.'/first-writing');
        $waitFor('second-writing');
        usleep(100_000);
    };
    ProviderLedger::creating($holdLock);
    ProviderLedger::updating($holdLock);
}

$request = Request::create($url, $method, server: [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    $header => $value,
], content: $body);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

echo json_encode([
    'status' => $response->getStatusCode(),
    'etag' => $response->headers->get('ETag'),
    'body' => $response->getContent(),
], JSON_THROW_ON_ERROR);
