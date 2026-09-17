<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(MasterDataSeeder::class);

        $agronomistPosition = Position::firstOrCreate(
            ['code' => 'AGRONOMIST'],
            ['name' => 'Agronomist'],
        );

        $bojonegoro = WorkLocation::firstOrCreate(
            ['code' => 'BOJONEGORO'],
            ['name' => 'Bojonegoro'],
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@ama.test'],
            [
                'name' => 'AMA Super Admin',
                'nip' => 'ADM-0001',
                'password' => bcrypt('password'),
                'is_active' => true,
            ],
        );
        if (! $admin->hasRole('SUPER_ADMIN')) {
            $admin->assignRole('SUPER_ADMIN');
        }

        $agronomist = User::firstOrCreate(
            ['email' => 'agronomist@ama.test'],
            [
                'name' => 'Test Agronomist',
                'nip' => 'AGR-0001',
                'phone' => '085815759516',
                'position_id' => $agronomistPosition->id,
                'work_location_id' => $bojonegoro->id,
                'password' => bcrypt('password'),
                'is_active' => true,
            ],
        );
        if (! $agronomist->hasRole('AGRONOMIST')) {
            $agronomist->assignRole('AGRONOMIST');
        }
    }
}
