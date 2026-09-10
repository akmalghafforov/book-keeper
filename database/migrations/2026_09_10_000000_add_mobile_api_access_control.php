<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('status')->default('active')->index();
            $t->timestamp('last_login_at')->nullable();
            $t->unsignedInteger('access_version')->default(1);
            $t->timestamp('suspended_at')->nullable();
            $t->text('suspension_reason')->nullable();
        });
        Schema::create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('is_protected')->default(false);
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->nullable();
            $t->timestamps();
        });
        Schema::create('user_roles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'role_id']);
        });
        Schema::create('role_permissions', function (Blueprint $t): void {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('api_endpoints', function (Blueprint $t): void {
            $t->id();
            $t->string('endpoint_key')->unique();
            $t->string('method', 10);
            $t->string('route_template');
            $t->string('required_permission')->nullable();
            $t->string('display_name');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('user_endpoint_overrides', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('api_endpoint_id')->constrained()->cascadeOnDelete();
            $t->enum('effect', ['allow', 'deny']);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'api_endpoint_id']);
        });
        Schema::create('mobile_auth_sessions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('device_id');
            $t->string('device_name');
            $t->string('platform')->nullable();
            $t->string('app_version')->nullable();
            $t->string('ip', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamp('last_used_at');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoked_reason')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'revoked_at']);
        });
        Schema::create('personal_access_tokens', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('session_id')->nullable();
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('mobile_refresh_tokens', function (Blueprint $t): void {
            $t->id();
            $t->uuid('session_id');
            $t->string('token', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->foreign('session_id')->references('id')->on('mobile_auth_sessions')->cascadeOnDelete();
        });
        Schema::create('idempotency_keys', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('route');
            $t->string('key');
            $t->string('request_hash', 64);
            $t->json('response');
            $t->unsignedSmallInteger('status_code');
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['user_id', 'route', 'key']);
        });
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('session_id')->nullable();
            $t->string('endpoint')->nullable();
            $t->string('action');
            $t->nullableMorphs('subject');
            $t->uuid('request_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('result')->default('success');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        $permissions = ['clients.view', 'clients.create', 'shops.create', 'catalogs.view', 'suppliers.view', 'suppliers.create', 'providers.view', 'debt_ledgers.view', 'debt_ledgers.create', 'debt_ledgers.update', 'debt_ledgers.delete', 'distributions.view', 'distributions.create', 'distributions.update', 'distributions.delete'];
        foreach ($permissions as $code) {
            DB::table('permissions')->insert(['code' => $code, 'name' => $code, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['super_admin', 'access_admin', 'ledger_manager', 'distribution_manager', 'viewer'] as $code) {
            DB::table('roles')->insert(['code' => $code, 'name' => str_replace('_', ' ', $code), 'is_protected' => in_array($code, ['super_admin', 'access_admin']), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($permissions as $permission) {
            DB::table('api_endpoints')->insert(['endpoint_key' => $permission, 'method' => '*', 'route_template' => '/api/v1', 'required_permission' => $permission, 'display_name' => $permission, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'idempotency_keys', 'mobile_refresh_tokens', 'personal_access_tokens', 'mobile_auth_sessions', 'user_endpoint_overrides', 'api_endpoints', 'role_permissions', 'user_roles', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', function (Blueprint $t): void {
            $t->dropColumn(['status', 'last_login_at', 'access_version', 'suspended_at', 'suspension_reason']);
        });
    }
};
