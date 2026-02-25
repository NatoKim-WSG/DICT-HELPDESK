<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $defaultUserPassword = (string) config('helpdesk.default_user_password', 'i0n3R3s0urc3s!');

        User::updateOrCreate(['email' => 'admin@ioneresources.net'], [
            'name' => 'Admin',
            'email' => 'admin@ioneresources.net',
            'phone' => '09763621490',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make($defaultUserPassword),
            'is_active' => true,
            'email_verified_at' => '2026-02-25 06:59:20',
        ]);

        $this->command?->info('Admin user synchronized from current database snapshot.');
    }
}
