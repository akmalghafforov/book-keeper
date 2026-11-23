<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPermissionBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_upgrade_grants_new_permissions_to_existing_admins(): void
    {
        $migration = '2026_11_23_000003_backfill_admin_permissions';
        DB::table('migrations')->where('migration', $migration)->delete();
        Role::where('code', 'admin_operations')->delete();

        $superAdminRole = Role::where('code', 'super_admin')->firstOrFail();
        $existingPermission = Permission::where('code', 'clients.create')->firstOrFail();
        $superAdminRole->permissions()->sync([$existingPermission->id]);

        $legacyAdmin = User::factory()->create(['email' => 'admin@example.com']);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach($superAdminRole);
        $otherUser = User::factory()->create();
        $otherUser->roles()->attach(Role::where('code', 'access_admin')->firstOrFail());

        $this->artisan('migrate', ['--force' => true, '--path' => "database/migrations/{$migration}.php"])
            ->assertSuccessful();
        $this->assertDatabaseHas('migrations', ['migration' => $migration]);

        // Re-running the backfill should preserve the same grants.
        $backfill = require database_path("migrations/{$migration}.php");
        $backfill->up();

        $newPermissions = [
            'provider_ledgers.view', 'provider_ledgers.create', 'provider_ledgers.update', 'provider_ledgers.delete',
            'transaction_drafts.submit', 'transaction_drafts.view', 'transaction_drafts.review', 'database.operate',
        ];

        $this->assertEqualsCanonicalizing($newPermissions, $legacyAdmin->permissions());
        $this->assertEqualsCanonicalizing([...$newPermissions, 'clients.create'], $superAdmin->permissions());
        $this->assertSame([], $otherUser->permissions());
    }
}
