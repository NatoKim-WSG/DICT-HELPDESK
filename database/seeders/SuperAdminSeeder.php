<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plainPassword = env('SEED_SUPER_ADMIN_PASSWORD', 'i0n3i0n3');

        User::updateOrCreate(['email' => 'admin@ione.com'], [
            'name' => 'Super Administrator',
            'email' => 'admin@ione.com',
            'phone' => '+1234567890',
            'department' => 'Administration',
            'role' => 'super_admin',
            'password' => Hash::make($plainPassword),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Super admin user seeded successfully.');
        $this->command->warn('Email: admin@ione.com');
        $this->command->warn("Password: {$plainPassword}");
        $this->command->warn('Default password is intended for local/dev use only.');
    }
}
