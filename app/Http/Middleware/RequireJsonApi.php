<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireJsonApi
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->acceptsJson()) {
            return response()->json(['message' => 'Accept: application/json is required.'], 406);
        }

return $next($request);
    }
}
