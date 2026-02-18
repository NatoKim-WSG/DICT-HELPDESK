<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@ioneresources.com'], [
            'name' => 'Admin User',
            'email' => 'admin@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'department' => 'IT',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'support@ioneresources.com'], [
            'name' => 'Support Admin',
            'email' => 'support@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'department' => 'IT Support',
            'phone' => '+1234567891',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'client@ioneresources.com'], [
            'name' => 'Test Client',
            'email' => 'client@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'department' => 'Sales',
            'phone' => '+1234567893',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'jane@ioneresources.com'], [
            'name' => 'Jane Doe',
            'email' => 'jane@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'department' => 'Marketing',
            'phone' => '+1234567894',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'bob@ioneresources.com'], [
            'name' => 'Bob Smith',
            'email' => 'bob@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'department' => 'Finance',
            'phone' => '+1234567895',
            'is_active' => true,
        ]);
    }
}
