<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultUserPassword = DefaultPasswordResolver::user();

        $users = [
            [
                'name' => 'Super User',
                'email' => 'cjose@ioneresources.net',
                'role' => User::ROLE_SUPER_USER,
                'department' => 'iOne',
                'phone' => '09763621492',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'Technical',
                'email' => 'xtianjose02@gmail.com',
                'role' => User::ROLE_TECHNICAL,
                'department' => 'iOne',
                'phone' => '09763621491',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'DICTR1',
                'email' => 'DICTR1@gmail.com',
                'role' => User::ROLE_CLIENT,
                'department' => 'DICT',
                'phone' => '01234567890',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'AFPR2',
                'email' => 'AFPR2@gmail.com',
                'role' => User::ROLE_CLIENT,
                'department' => 'AFP',
                'phone' => '01234567890',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'AFPR1',
                'email' => 'AFPR1@gmail.com',
                'role' => User::ROLE_CLIENT,
                'department' => 'AFP',
                'phone' => '01234567890',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'Technical2',
                'email' => 'Technical2@ioneresources.net',
                'role' => User::ROLE_TECHNICAL,
                'department' => 'iOne',
                'phone' => '09763621493',
                'is_active' => true,
                'email_verified_at' => null,
            ],
        ];

        foreach ($users as $user) {
            $user['password'] = Hash::make($defaultUserPassword);

            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }

        $this->command?->info('UserSeeder synchronized from current database snapshot.');
    }
}
