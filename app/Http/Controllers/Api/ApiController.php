<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    protected function resource(Model $model, string $type, array $attributes = [], array $relationships = []): array
    {
        foreach ($attributes as $k => $v) {
            if (is_float($v) || is_int($v) && str_contains($k, 'amount')) {
                $attributes[$k] = (string) $v;
            }
        }

        return ['data' => ['type' => $type, 'id' => (string) $model->id, 'attributes' => $attributes ?: $model->attributesToArray(), 'relationships' => $relationships]];
    }

    protected function collection($paginator, string $type, callable $map): array
    {
        return ['data' => collect($paginator->items())->map($map)->values(), 'meta' => ['pagination' => ['page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()]]];
    }

    protected function etag(Model $model): string
    {
        return '"'.sha1($model->getKey().'|'.optional($model->updated_at)->format('U.u')).'"';
    }

    protected function requireMatch(Request $request, Model $model): ?JsonResponse
    {
        if ($request->header('If-Match') !== $this->etag($model)) {
            return response()->json(['message' => 'The resource has changed.'], 412);
        }

        return null;
    }

    protected function idempotent(Request $request, Closure $create): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || trim($key) === '') {
            return response()->json(['errors' => ['Idempotency-Key' => ['The Idempotency-Key header is required.']]], 422);
        }
        if (strlen($key) > 255) {
            return response()->json(['errors' => ['Idempotency-Key' => ['The Idempotency-Key header must be at most 255 bytes.']]], 422);
        }

        $scope = ['user_id' => $request->user()->id, 'route' => $request->path(), 'key' => $key];
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($request->all()));

        return DB::transaction(function () use ($scope, $hash, $create) {
            $now = now();

            // Reserve the unique key before reading it. The upsert serializes concurrent
            // requests and starts a write transaction before SQLite takes a read snapshot.
            DB::table('idempotency_keys')->upsert([
                ...$scope,
                'request_hash' => $hash,
                'response' => 'null',
                'status_code' => 0,
                'expires_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys($scope), ['route']);

            $keyQuery = DB::table('idempotency_keys')->where($scope);
            $stored = $keyQuery->lockForUpdate()->first();
            if (Date::parse($stored->expires_at)->isFuture()) {
                if ($stored->request_hash !== $hash) {
                    return response()->json(['message' => 'Idempotency key was used with a different request.'], 409);
                }

                return JsonResponse::fromJsonString(
                    $stored->response,
                    $stored->status_code,
                    $stored->response_etag === null ? [] : ['ETag' => $stored->response_etag],
                );
            }

            $response = $create();
            $now = now();
            $keyQuery->update([
                'request_hash' => $hash,
                'response' => $response->getContent(),
                'response_etag' => $response->headers->get('ETag'),
                'status_code' => $response->getStatusCode(),
                'expires_at' => $now->copy()->addHours(24),
                'updated_at' => $now,
            ]);

            return $response;
        }, 5);
    }
}
