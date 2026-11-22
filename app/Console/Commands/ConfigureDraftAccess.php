<?php

namespace App\Console\Commands;

use App\Http\Middleware\EndpointAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ConfigureDraftAccess extends Command
{
    protected $signature = 'drafts:configure-access
        {--reviewer=* : Existing active human reviewer email}
        {--operator=* : Existing active human database operator email}
        {--codex= : Dedicated API-only account email}';

    protected $description = 'Grant draft and database access to designated accounts';

    public function handle(): int
    {
        if (! $this->option('reviewer') && ! $this->option('operator') && ! $this->option('codex')) {
            $this->error('Designate at least one account.');

            return self::FAILURE;
        }

        $codexEmail = $this->option('codex');
        if ($codexEmail && ! filter_var($codexEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid email for the Codex account.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($codexEmail): void {
                foreach (['reviewer' => 'transaction_drafts.review', 'operator' => 'database.operate'] as $option => $permission) {
                    foreach ($this->option($option) as $email) {
                        $user = User::where('email', $email)->where('status', 'active')->where('api_only', false)->first();
                        if (! $user) {
                            throw new \DomainException('No active human account was found for '.$email);
                        }

                        $this->grant($user, 'draft_'.$option, [$permission]);
                    }
                }

                if ($codexEmail) {
                    $user = User::where('email', $codexEmail)->first();
                    if ($user && (! $user->api_only || $user->status !== 'active')) {
                        throw new \DomainException('Choose a dedicated active API-only account.');
                    }
                    if (! $user) {
                        $password = $this->secret('Password for the new Codex account (at least 16 characters)');
                        if (! is_string($password) || strlen($password) < 16) {
                            throw new \DomainException('The Codex password must have at least 16 characters.');
                        }
                        $user = new User(['email' => $codexEmail, 'name' => 'Codex', 'password' => Hash::make($password), 'status' => 'active']);
                        $user->api_only = true;
                        $user->save();
                    }

                    $this->grant($user, 'codex_draft_importer', EndpointAccess::API_ONLY_ENDPOINTS);
                }
            });
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Draft access configured.');

        return self::SUCCESS;
    }

    private function grant(User $user, string $code, array $permissions): void
    {
        $role = Role::firstOrCreate(['code' => $code], ['name' => str_replace('_', ' ', $code)]);
        $ids = Permission::whereIn('code', $permissions)->pluck('id');
        if ($ids->count() !== count($permissions)) {
            throw new \DomainException('Run the database migrations before configuring draft access.');
        }
        $role->permissions()->sync($ids);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
