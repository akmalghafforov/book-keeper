<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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

    protected function requireMatch(Request $request, Model $model): ?\Illuminate\Http\JsonResponse
    {
        if ($request->header('If-Match') !== $this->etag($model)) {
            return response()->json(['message' => 'The resource has changed.'], 412);
        }

return null;
    }

    protected function idempotent(Request $request, \Closure $create)
    {
        $key = $request->header('Idempotency-Key');
        if (! $key) {
            return response()->json(['errors' => ['Idempotency-Key' => ['The Idempotency-Key header is required.']]], 422);
        } $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($request->all()));
        $old = \Illuminate\Support\Facades\DB::table('idempotency_keys')->where(['user_id' => $request->user()->id, 'route' => $request->path(), 'key' => $key])->where('expires_at', '>', now())->first();
        if ($old) {
            if ($old->request_hash !== $hash) {
                return response()->json(['message' => 'Idempotency key was used with a different request.'], 409);
            }

return response()->json(json_decode($old->response, true), $old->status_code);
        } $response = $create();
        \Illuminate\Support\Facades\DB::table('idempotency_keys')->insert(['user_id' => $request->user()->id, 'route' => $request->path(), 'key' => $key, 'request_hash' => $hash, 'response' => json_encode($response->getData(true)), 'status_code' => $response->getStatusCode(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);

        return $response;
    }
}
