<?php

namespace Tests\Feature\Notifications;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('announcements.send');
        Role::findOrCreate('ADMIN')->givePermissionTo('announcements.send');
        Role::findOrCreate('AGRONOMIST');

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    private function agronomist(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('AGRONOMIST');

        return $user;
    }

    public function test_it_requires_announcements_send(): void
    {
        $this->actingAs(User::factory()->create())->get('/announcements')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post('/announcements', ['title' => 't', 'body' => 'b'])
            ->assertForbidden();
    }

    public function test_it_notifies_only_active_users_matching_the_target(): void
    {
        $admin = $this->admin();
        $location = WorkLocation::create(['name' => 'Bojonegoro', 'code' => 'BJN', 'is_active' => true]);
        $inScope = $this->agronomist(['work_location_id' => $location->id]);
        $wrongLocation = $this->agronomist();
        $inactive = $this->agronomist(['work_location_id' => $location->id, 'is_active' => false]);

        $this->actingAs($admin)->post('/announcements', [
            'title' => 'Libur',
            'body' => 'Besok libur.',
            'role' => 'AGRONOMIST',
            'work_location_id' => $location->id,
        ])->assertSessionHas('success', 'Pengumuman dikirim ke 1 pengguna.');

        $this->assertSame(1, Notification::where('type', 'ANNOUNCEMENT')->count());
        $this->assertTrue(Notification::where('user_id', $inScope->id)->where('title', 'Libur')->exists());
        $this->assertFalse(Notification::where('user_id', $wrongLocation->id)->exists());
        $this->assertFalse(Notification::where('user_id', $inactive->id)->exists());

        $audit = AuditLog::where('action', 'announced')->firstOrFail();
        $this->assertSame(1, $audit->new_values['recipients']);
    }

    public function test_without_a_target_it_reaches_every_active_user_and_validates_input(): void
    {
        $admin = $this->admin();
        $this->agronomist();
        $this->agronomist();

        $this->actingAs($admin)->post('/announcements', ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);
        $this->actingAs($admin)->post('/announcements', ['title' => 't', 'body' => 'b', 'role' => 'NOPE'])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)->post('/announcements', ['title' => 'Halo', 'body' => 'Semua'])
            ->assertSessionHas('success', 'Pengumuman dikirim ke 3 pengguna.'); // 2 agronomists + the admin
    }
}
