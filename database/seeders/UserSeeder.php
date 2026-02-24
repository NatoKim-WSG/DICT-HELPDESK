<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $plainPassword = env('SEED_DEFAULT_USER_PASSWORD', 'i0n3i0n3');

        User::updateOrCreate(['email' => 'admin@ioneresources.com'], [
            'name' => 'Super User',
            'email' => 'admin@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'super_user',
            'department' => 'IT',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'support@ioneresources.com'], [
            'name' => 'Support Technical',
            'email' => 'support@ioneresources.com',
            'password' => Hash::make($plainPassword),
            'role' => 'technical',
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

        if ($this->command) {
            $this->command->warn("Seeded users default password: {$plainPassword}");
        }
    }
}
