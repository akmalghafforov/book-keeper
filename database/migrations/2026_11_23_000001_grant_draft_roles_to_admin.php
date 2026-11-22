<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLES = [
        'draft_reviewer' => ['transaction_drafts.review'],
        'draft_operator' => ['database.operate'],
        'codex_draft_importer' => [
            'transaction_drafts.submit', 'transaction_drafts.view',
            'clients.view', 'catalogs.view', 'suppliers.view', 'providers.view',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $adminId = DB::table('users')->where('email', 'admin@example.com')
                ->where('api_only', false)->where('status', 'active')->value('id');

            foreach (self::ROLES as $code => $permissions) {
                DB::table('roles')->insertOrIgnore([
                    'code' => $code,
                    'name' => str_replace('_', ' ', $code),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $roleId = DB::table('roles')->where('code', $code)->value('id');
                $permissionIds = DB::table('permissions')->whereIn('code', $permissions)->pluck('id');
                if ($permissionIds->count() !== count($permissions)) {
                    throw new RuntimeException("Missing permissions for role {$code}.");
                }

                DB::table('role_permissions')->insertOrIgnore($permissionIds->map(fn ($permissionId) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ])->all());

                if ($adminId !== null) {
                    DB::table('user_roles')->insertOrIgnore([
                        'user_id' => $adminId,
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Keep grants that may already have been assigned by drafts:configure-access.
    }
};
