<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'department' => 'IT',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        // Create Agent Users
        User::create([
            'name' => 'John Agent',
            'email' => 'agent1@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'department' => 'IT Support',
            'phone' => '+1234567891',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Sarah Support',
            'email' => 'agent2@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'department' => 'IT Support',
            'phone' => '+1234567892',
            'is_active' => true,
        ]);

        // Create Client Users
        User::create([
            'name' => 'Test Client',
            'email' => 'client@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'department' => 'Sales',
            'phone' => '+1234567893',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@ioneresources.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'department' => 'Marketing',
            'phone' => '+1234567894',
            'is_active' => true,
        ]);

        User::create([
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