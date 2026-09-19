<?php

namespace Modules\Identity\Presentation\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * The way to create the first account on a real (non-local) install.
 * DatabaseSeeder deliberately does not create accounts with a well-known
 * password outside local/testing, so without this command a fresh
 * production database would have nobody who can log in.
 */
class CreateAdmin extends Command
{
    protected $signature = 'ama:create-admin
        {email : Login email}
        {--name= : Display name (asked for if omitted)}
        {--role=SUPER_ADMIN : Role to assign}';

    protected $description = 'Create an active admin user (prompts for the password; it is never taken from the command line)';

    public function handle(): int
    {
        $role = (string) $this->option('role');

        if (! Role::where('name', $role)->exists()) {
            $this->error("Role {$role} does not exist. Run: php artisan db:seed --class=RolesAndPermissionsSeeder");

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) $this->secret('Password (min 8 characters)');

        if ($password !== (string) $this->secret('Repeat password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $this->argument('email'), 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $this->argument('email'),
            'password' => $password,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $this->info("Created {$role} user {$user->email} (id {$user->id}).");

        return self::SUCCESS;
    }
}
