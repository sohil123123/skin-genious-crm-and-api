<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        if ($this->command->confirm('Do you wish to refresh migration before seeding, it will clear all old data ?')) {
            $this->command->call('migrate:refresh');
            $this->command->warn("Data cleared, starting from blank database.");

            $this->call(PermissionSeeder::class);
            $this->call(RoleSeeder::class);
            $this->call(ClinicsTableSeeder::class);
            $this->call(UserSeeder::class);

            $this->command->call('shield:generate', [
                '--panel' => 'admin',
                '--all' => true,
                '--option' => 'policies_and_permissions',
                '--no-interaction' => true,
                '--ignore-existing-policies' => true,
            ]);
            $this->command->info('Database was refreshed.');
            
    }
    }
}
