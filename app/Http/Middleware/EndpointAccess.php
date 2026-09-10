<?php

namespace App\Http\Middleware;

use App\Models\ApiEndpoint;
use Closure;
use Illuminate\Http\Request;

class EndpointAccess
{
    public function handle(Request $request, Closure $next, string $key)
    {
        $endpoint = ApiEndpoint::where('endpoint_key', $key)->first();
        if (! $endpoint || ! $endpoint->active || ! $request->user()?->canEndpoint($key, $endpoint->required_permission)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

return $next($request);
    }
}
