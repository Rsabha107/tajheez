<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Material Planning demo accounts (all password: "password"):
     * - test@example.com           — plain user, no role
     * - domain-manager@example.com — role "domain-manager", managed_domain IT
     * - admin@example.com          — role "admin"
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'domain-manager', 'guard_name' => 'web']);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'Jordan Kim',
            'email' => 'domain-manager@example.com',
            'managed_domain' => 'IT',
        ])->assignRole('domain-manager');

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ])->assignRole('admin');

        $this->call(MaterialPlanningSeeder::class);
    }
}
