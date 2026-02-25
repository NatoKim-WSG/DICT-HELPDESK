<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ShadowSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'shadow@ione.com'], [
            'name' => 'Shadow',
            'email' => 'shadow@ione.com',
            'phone' => '+1234567899',
            'department' => 'Administration',
            'role' => User::ROLE_SHADOW,
            'password' => '$2y$12$wjvmPQLaXpQaZOXMIYOOkOqqU0O.r/Zv88awBsm5M1X3qHn0YEzLi',
            'is_active' => true,
            'email_verified_at' => '2026-02-25 06:59:18',
        ]);

        $this->command?->info('Shadow user synchronized from current database snapshot.');
    }
}
