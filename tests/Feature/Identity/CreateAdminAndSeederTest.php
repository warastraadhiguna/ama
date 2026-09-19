<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_the_dev_accounts_in_the_testing_environment(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertTrue(User::where('email', 'admin@ama.test')->exists());
        $this->assertTrue(User::where('email', 'agronomist@ama.test')->exists());
    }

    public function test_the_seeder_creates_no_well_known_accounts_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertSame(0, User::count());
        // The roles/permissions are still seeded — production needs them.
        $this->assertDatabaseHas('roles', ['name' => 'SUPER_ADMIN']);
    }

    public function test_create_admin_makes_an_active_super_admin_with_a_hashed_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('ama:create-admin', ['email' => 'owner@example.test', '--name' => 'Owner'])
            ->expectsQuestion('Password (min 8 characters)', 'a-long-secret')
            ->expectsQuestion('Repeat password', 'a-long-secret')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('SUPER_ADMIN'));
        $this->assertNotSame('a-long-secret', $user->password);
        $this->assertTrue(password_verify('a-long-secret', $user->password));
    }

    public function test_create_admin_rejects_mismatched_short_or_duplicate_input(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('ama:create-admin', ['email' => 'a@example.test', '--name' => 'A'])
            ->expectsQuestion('Password (min 8 characters)', 'a-long-secret')
            ->expectsQuestion('Repeat password', 'different-one')
            ->assertFailed();

        $this->artisan('ama:create-admin', ['email' => 'a@example.test', '--name' => 'A'])
            ->expectsQuestion('Password (min 8 characters)', 'short')
            ->expectsQuestion('Repeat password', 'short')
            ->assertFailed();

        User::factory()->create(['email' => 'dup@example.test']);
        $this->artisan('ama:create-admin', ['email' => 'dup@example.test', '--name' => 'D'])
            ->expectsQuestion('Password (min 8 characters)', 'a-long-secret')
            ->expectsQuestion('Repeat password', 'a-long-secret')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_create_admin_fails_clearly_when_roles_are_not_seeded(): void
    {
        $this->artisan('ama:create-admin', ['email' => 'x@example.test', '--name' => 'X'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
