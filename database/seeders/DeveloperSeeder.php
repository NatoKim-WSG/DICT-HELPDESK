<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DeveloperSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'developer@ione.com'], [
            'name' => 'Developer',
            'email' => 'developer@ione.com',
            'phone' => '+1234567899',
            'department' => 'Administration',
            'role' => User::ROLE_DEVELOPER,
            'password' => '$2y$12$wjvmPQLaXpQaZOXMIYOOkOqqU0O.r/Zv88awBsm5M1X3qHn0YEzLi',
            'is_active' => true,
            'email_verified_at' => '2026-02-25 06:59:18',
        ]);

        $this->command?->info('Developer user synchronized from current database snapshot.');
    }
}
