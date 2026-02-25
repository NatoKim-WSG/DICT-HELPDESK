<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super User',
                'email' => 'cjose@ioneresources.net',
                'password' => '$2y$12$SXwlctsHaBJY6X211nJdWeC1yaqGPIFIOCoOjelEzo.g3uc3CMDM2',
                'role' => User::ROLE_SUPER_USER,
                'department' => 'iOne',
                'phone' => '09763621492',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'Technical',
                'email' => 'xtianjose02@gmail.com',
                'password' => '$2y$12$J8XYDHT7xUdlie27f3p49uEq4zpMvdF4BSzRv5xV7Pw8uyodbiKF.',
                'role' => User::ROLE_TECHNICAL,
                'department' => 'iOne',
                'phone' => '09763621491',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'DICTR1',
                'email' => 'DICTR1@gmail.com',
                'password' => '$2y$12$MHiZ7rcl/ss/SqrMDYvCMuofSSYs.0t.yi8u98SnFqbSyh9aHEhpS',
                'role' => User::ROLE_CLIENT,
                'department' => 'DICT',
                'phone' => '01234567890',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'AFPR2',
                'email' => 'AFPR2@gmail.com',
                'password' => '$2y$12$I75IGfa1xhba2k8.cZlcMuomFXHG8bw9IbLvt7fJRbT9tHnrvnP3i',
                'role' => User::ROLE_CLIENT,
                'department' => 'AFP',
                'phone' => '01234567890',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'AFPR1',
                'email' => 'AFPR1@gmail.com',
                'password' => '$2y$12$KauJiZsKDhbC4YOZeI/R4eMG3TlQscIukpvOCEdllocFeos.fuKPO',
                'role' => User::ROLE_CLIENT,
                'department' => 'AFP',
                'phone' => '01234567890',
                'is_active' => true,
                'email_verified_at' => null,
            ],
            [
                'name' => 'Technical2',
                'email' => 'Technical2@ioneresources.net',
                'password' => '$2y$12$JTdsUfHW3/kgnXM000gGBOxmKWwJiVD.WH6URGc/zHbOToL/6mLFy',
                'role' => User::ROLE_TECHNICAL,
                'department' => 'iOne',
                'phone' => '09763621493',
                'is_active' => true,
                'email_verified_at' => null,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }

        $this->command?->info('UserSeeder synchronized from current database snapshot.');
    }
}
