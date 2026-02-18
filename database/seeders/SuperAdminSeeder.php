<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if super admin already exists
        if (User::where('role', 'super_admin')->exists()) {
            $this->command->info('Super admin user already exists.');
            return;
        }

        // Create the super admin user
        User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@ione.com',
            'phone' => '+1234567890',
            'department' => 'Administration',
            'role' => 'super_admin',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Super admin user created successfully.');
        $this->command->warn('Email: admin@ione.com');
        $this->command->warn('Password: password123');
        $this->command->error('Please change the password after first login!');
    }
}
