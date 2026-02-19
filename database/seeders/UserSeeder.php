<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $plainPassword = env('SEED_DEFAULT_USER_PASSWORD');
        $passwordWasGenerated = false;

        if (!$plainPassword) {
            $plainPassword = Str::random(20);
            $passwordWasGenerated = true;
        }

        User::updateOrCreate(['email' => 'admin@ioneresources.com'], [
            'name' => 'Admin User',
            'email' => 'admin@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'admin',
            'department' => 'IT',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'support@ioneresources.com'], [
            'name' => 'Support Technician',
            'email' => 'support@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'technician',
            'department' => 'IT Support',
            'phone' => '+1234567891',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'client@ioneresources.com'], [
            'name' => 'Test Client',
            'email' => 'client@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'client',
            'department' => 'Sales',
            'phone' => '+1234567893',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'jane@ioneresources.com'], [
            'name' => 'Jane Doe',
            'email' => 'jane@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'client',
            'department' => 'Marketing',
            'phone' => '+1234567894',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'bob@ioneresources.com'], [
            'name' => 'Bob Smith',
            'email' => 'bob@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'client',
            'department' => 'Finance',
            'phone' => '+1234567895',
            'is_active' => true,
        ]);

        if ($passwordWasGenerated && $this->command) {
            $this->command->warn('SEED_DEFAULT_USER_PASSWORD is not set. A temporary password was generated for seeded users.');
            $this->command->warn("Seeded users temporary password: {$plainPassword}");
            $this->command->error('Set SEED_DEFAULT_USER_PASSWORD in .env before seeding non-local environments.');
        }
    }
}
