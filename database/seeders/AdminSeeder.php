<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $defaultUserPassword = DefaultPasswordResolver::user();

        User::updateOrCreate(['email' => 'admin@ioneresources.net'], [
            'name' => 'Admin',
            'email' => 'admin@ioneresources.net',
            'phone' => '09763621490',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make($defaultUserPassword),
            'password_reveal' => $defaultUserPassword,
            'is_active' => true,
            'email_verified_at' => '2026-02-25 06:59:20',
        ]);

        $this->command?->info('Admin user synchronized from current database snapshot.');
    }
}
