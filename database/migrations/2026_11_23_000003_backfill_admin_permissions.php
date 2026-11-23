<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'provider_ledgers.view',
        'provider_ledgers.create',
        'provider_ledgers.update',
        'provider_ledgers.delete',
        'transaction_drafts.submit',
        'transaction_drafts.view',
        'transaction_drafts.review',
        'database.operate',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            DB::table('roles')->insertOrIgnore([
                'code' => 'admin_operations',
                'name' => 'admin operations',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissions = DB::table('permissions')->whereIn('code', self::PERMISSIONS);
            if ($permissions->count() !== count(self::PERMISSIONS)) {
                throw new RuntimeException('Missing permissions for the admin access backfill.');
            }

            $grants = DB::table('roles')->crossJoinSub($permissions->select('id'), 'new_permissions')
                ->select('roles.id', 'new_permissions.id')
                ->whereIn('roles.code', ['admin_operations', 'super_admin']);

            DB::table('role_permissions')->insertOrIgnoreUsing(['role_id', 'permission_id'], $grants);

            // The original admin account can predate mobile API roles.
            $adminId = DB::table('users')->where('email', 'admin@example.com')
                ->where('status', 'active')->where('api_only', false)->value('id');

            if ($adminId !== null) {
                DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $adminId,
                    'role_id' => DB::table('roles')->where('code', 'admin_operations')->value('id'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve grants that may also have been assigned independently.
    }
};
