<?php

namespace App\Http\Middleware;

use App\Models\ApiEndpoint;
use Closure;
use Illuminate\Http\Request;

class EndpointAccess
{
    public const API_ONLY_ENDPOINTS = [
        'transaction_drafts.submit', 'transaction_drafts.view',
        'clients.view', 'catalogs.view', 'suppliers.view', 'providers.view',
    ];

    public function handle(Request $request, Closure $next, string $key)
    {
        if ($request->user()?->api_only && ! in_array($key, self::API_ONLY_ENDPOINTS, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $endpoint = ApiEndpoint::where('endpoint_key', $key)->first();
        if (! $endpoint || ! $endpoint->active || ! $request->user()?->canEndpoint($key, $endpoint->required_permission)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
