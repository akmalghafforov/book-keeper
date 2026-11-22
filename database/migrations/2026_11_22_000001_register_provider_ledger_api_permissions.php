<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROUTES = [
        'view' => ['GET', '/api/v1/provider-ledgers'],
        'create' => ['POST', '/api/v1/provider-ledgers'],
        'update' => ['PATCH', '/api/v1/provider-ledgers/{providerLedger}'],
        'delete' => ['DELETE', '/api/v1/provider-ledgers/{providerLedger}'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach (self::ROUTES as $action => [$method, $route]) {
                $code = 'provider_ledgers.'.$action;
                $permissionId = DB::table('permissions')->insertGetId([
                    'code' => $code,
                    'name' => $code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('api_endpoints')->insert([
                    'endpoint_key' => $code,
                    'method' => $method,
                    'route_template' => $route,
                    'required_permission' => $code,
                    'display_name' => $code,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $roleGrants = DB::table('role_permissions')
                    ->selectRaw('role_id, ?', [$permissionId])
                    ->where('permission_id', DB::table('permissions')->select('id')->where('code', 'debt_ledgers.'.$action));
                DB::table('role_permissions')->insertUsing(['role_id', 'permission_id'], $roleGrants);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $codes = array_map(fn (string $action) => 'provider_ledgers.'.$action, array_keys(self::ROUTES));
            DB::table('api_endpoints')->whereIn('endpoint_key', $codes)->delete();
            DB::table('permissions')->whereIn('code', $codes)->delete();
        });
    }
};
