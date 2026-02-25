<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@ioneresources.net'], [
            'name' => 'Admin',
            'email' => 'admin@ioneresources.net',
            'phone' => '09763621490',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => '$2y$12$aUoOss2c9n2.C/x/kZ0a0uR2ygerDP82mIEzJ6H8gO7WCuirAsFXu',
            'is_active' => true,
            'email_verified_at' => '2026-02-25 06:59:20',
        ]);

        $this->command?->info('Admin user synchronized from current database snapshot.');
    }
}
