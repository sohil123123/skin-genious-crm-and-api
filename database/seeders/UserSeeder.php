<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

use App\Models\User;

use DB;

class UserSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'mobile' => '1234567890',
                'password' => bcrypt('123456'),
                'email_verified_at' => now(),
            ]
        )
        ->assignRole('admin');

        // Create therapist user
        User::firstOrCreate(
            ['email' => 'therapist@gmail.com'],
            [
                'first_name' => 'Therapist',
                'last_name' => 'User',
                'mobile' => '1234567891',
                'password' => bcrypt('123456'),
                'email_verified_at' => now(),
            ]
        )
        ->assignRole('therapist');

        // Create user
        User::firstOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'first_name' => 'Customer',
                'last_name' => 'User',
                'mobile' => '1234567892',
                'password' => bcrypt('123456'),
                'email_verified_at' => now(),
            ]
        )
        ->assignRole('user');

    }
}
