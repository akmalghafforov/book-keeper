<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HumanWebAccess
{
    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $user = $request->user();
        abort_unless($user && ! $user->api_only, 403);
        if ($permission !== null) {
            abort_unless($user->status === 'active' && in_array($permission, $user->permissions(), true), 403);
        }

        return $next($request);
    }
}
