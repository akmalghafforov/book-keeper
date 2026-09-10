<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'device_id' => 'required|string|max:255', 'device_name' => 'required|string|max:255', 'platform' => 'nullable|string|max:50', 'app_version' => 'nullable|string|max:50']);
        $u = User::where('email', $v['email'])->first();
        if (! $u || ! Hash::check($v['password'], $u->password) || $u->status !== 'active') {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

return response()->json($this->issue($u, $v, $r));
    }

    public function refresh(Request $r)
    {
        $v = $r->validate(['refresh_token' => 'required|string', 'session_id' => 'required|uuid']);

        return DB::transaction(function () use ($v, $r) {
            $refresh = DB::table('mobile_refresh_tokens')->where('token', hash('sha256', $v['refresh_token']))->lockForUpdate()->first();
            $session = DB::table('mobile_auth_sessions')->where('id', $v['session_id'])->lockForUpdate()->first();
            if (! $refresh || ! $session || $refresh->session_id !== $session->id || $session->revoked_at || $session->expires_at < now() || $refresh->revoked_at || $refresh->expires_at < now()) {
                return response()->json(['message' => 'Invalid refresh token.'], 401);
            } if ($refresh->used_at) {
                DB::table('mobile_auth_sessions')->where('id', $session->id)->update(['revoked_at' => now(), 'revoked_reason' => 'refresh_replay']);

                return response()->json(['message' => 'Refresh token replay detected.'], 401);
            } DB::table('mobile_refresh_tokens')->where('id', $refresh->id)->update(['used_at' => now()]);

            return response()->json($this->issue(User::findOrFail($session->user_id), ['device_id' => $session->device_id, 'device_name' => $session->device_name, 'platform' => $session->platform, 'app_version' => $session->app_version], $r, $session->id));
        });
    }

    public function logout(Request $r)
    {
        $token = $r->attributes->get('mobile_token');
        DB::table('personal_access_tokens')->where('id', $token->id)->update(['revoked_at' => now()]);
        if ($token->session_id) {
            DB::table('mobile_auth_sessions')->where('id', $token->session_id)->update(['revoked_at' => now(), 'revoked_reason' => 'logout']);
        }

return response()->noContent();
    }

    public function logoutAll(Request $r)
    {
        $r->validate(['password' => 'nullable|string']);
        if ($r->filled('password') && ! Hash::check($r->password, $r->user()->password)) {
            return response()->json(['errors' => ['password' => ['The password is incorrect.']]], 422);
        } $ids = DB::table('mobile_auth_sessions')->where('user_id', $r->user()->id)->whereNull('revoked_at')->pluck('id');
        DB::table('mobile_auth_sessions')->whereIn('id', $ids)->update(['revoked_at' => now(), 'revoked_reason' => 'logout_all']);
        DB::table('personal_access_tokens')->whereIn('session_id', $ids)->update(['revoked_at' => now()]);

        return response()->noContent();
    }

    public function me(Request $r)
    {
        $u = $r->user();

        return response()->json(['data' => ['type' => 'user', 'id' => (string) $u->id, 'attributes' => ['name' => $u->name, 'email' => $u->email, 'status' => $u->status], 'relationships' => ['roles' => $u->roles->pluck('code')->values(), 'permissions' => $u->permissions()]]]);
    }

    public function sessions(Request $r)
    {
        $current = $r->attributes->get('mobile_token')->session_id;

        return response()->json(['data' => DB::table('mobile_auth_sessions')->where('user_id', $r->user()->id)->whereNull('revoked_at')->get()->map(fn ($s) => ['id' => $s->id, 'device_id' => $s->device_id, 'device_name' => $s->device_name, 'platform' => $s->platform, 'last_used_at' => $s->last_used_at, 'expires_at' => $s->expires_at, 'current' => $s->id === $current])]);
    }

    public function revokeSession(Request $r, string $session)
    {
        DB::table('mobile_auth_sessions')->where('id', $session)->where('user_id', $r->user()->id)->update(['revoked_at' => now(), 'revoked_reason' => 'user_revoked']);

        return response()->noContent();
    }

    private function issue(User $u, array $device, Request $r, ?string $sessionId = null): array
    {
        $sessionId ??= (string) Str::uuid();
        $now = now();
        if (! DB::table('mobile_auth_sessions')->where('id', $sessionId)->exists()) {
            DB::table('mobile_auth_sessions')->insert(['id' => $sessionId, 'user_id' => $u->id, 'device_id' => $device['device_id'], 'device_name' => $device['device_name'], 'platform' => $device['platform'] ?? null, 'app_version' => $device['app_version'] ?? null, 'ip' => $r->ip(), 'user_agent' => $r->userAgent(), 'last_used_at' => $now, 'expires_at' => $now->copy()->addDays(30), 'created_at' => $now, 'updated_at' => $now]);
            $ids = DB::table('mobile_auth_sessions')->where('user_id', $u->id)->whereNull('revoked_at')->orderByDesc('last_used_at')->pluck('id');
            foreach ($ids->slice(5) as $id) {
                DB::table('mobile_auth_sessions')->where('id', $id)->update(['revoked_at' => $now, 'revoked_reason' => 'session_limit']);
            }
        } $access = Str::random(64);
        $refresh = Str::random(80);
        DB::table('personal_access_tokens')->insert(['user_id' => $u->id, 'session_id' => $sessionId, 'name' => 'mobile', 'token' => hash('sha256', $access), 'expires_at' => $now->copy()->addMinutes(15), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('mobile_refresh_tokens')->insert(['session_id' => $sessionId, 'token' => hash('sha256', $refresh), 'expires_at' => $now->copy()->addDays(30), 'created_at' => $now, 'updated_at' => $now]);
        $u->update(['last_login_at' => $now]);

        return ['access_token' => $access, 'expires_in' => 900, 'refresh_token' => $refresh, 'session_id' => $sessionId, 'user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email], 'permissions' => $u->permissions()];
    }
}
