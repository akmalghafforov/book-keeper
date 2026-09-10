<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $raw = $request->bearerToken();
        if (! $raw) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $token = DB::table('personal_access_tokens')->where('token', hash('sha256', $raw))->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $user = \App\Models\User::find($token->user_id);
        $session = $token->session_id ? DB::table('mobile_auth_sessions')->where('id', $token->session_id)->whereNull('revoked_at')->where('expires_at', '>', now())->first() : null;
        if (! $user || $user->status !== 'active' || ($token->session_id && ! $session)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        DB::table('personal_access_tokens')->where('id', $token->id)->update(['last_used_at' => now()]);
        if ($session) {
            DB::table('mobile_auth_sessions')->where('id', $session->id)->update(['last_used_at' => now()]);
        }
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile_token', $token);

        return $next($request);
    }
}
