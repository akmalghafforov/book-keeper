<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = ['clients.view', 'clients.create', 'shops.create', 'catalogs.view', 'suppliers.view', 'suppliers.create', 'providers.view', 'debt_ledgers.view', 'debt_ledgers.create', 'debt_ledgers.update', 'debt_ledgers.delete', 'distributions.view', 'distributions.create', 'distributions.update', 'distributions.delete', 'access_control.manage'];
        foreach ($permissions as $code) {
            \App\Models\Permission::firstOrCreate(['code' => $code], ['name' => $code]);
        }
        $roles = ['super_admin', 'access_admin', 'ledger_manager', 'distribution_manager', 'viewer'];
        foreach ($roles as $code) {
            $role = \App\Models\Role::firstOrCreate(['code' => $code], ['name' => str_replace('_', ' ', $code), 'is_protected' => in_array($code, ['super_admin', 'access_admin'])]);
            if ($code === 'super_admin') {
                $role->permissions()->sync(\App\Models\Permission::pluck('id'));
            }
        }
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->call([
            ClientSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
