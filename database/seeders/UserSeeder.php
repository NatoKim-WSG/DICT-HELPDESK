<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $staffDefaultPassword = DefaultPasswordResolver::staff();
        $clientPasswordMode = DefaultPasswordResolver::clientPasswordMode();
        $clientFixedPassword = DefaultPasswordResolver::clientFixed();
        $generatedClientCredentials = [];

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
            $isClient = User::normalizeRole((string) ($user['role'] ?? '')) === User::ROLE_CLIENT;
            $resolvedPassword = $isClient
                ? ($clientPasswordMode === DefaultPasswordResolver::CLIENT_PASSWORD_MODE_RANDOM
                    ? DefaultPasswordResolver::generateRandomClientPassword(10)
                    : $clientFixedPassword)
                : $staffDefaultPassword;

            $user['password'] = Hash::make($resolvedPassword);
            $user['must_change_password'] = ! $isClient;

            $persistedUser = User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );

            if ($isClient && $clientPasswordMode === DefaultPasswordResolver::CLIENT_PASSWORD_MODE_RANDOM) {
                $generatedClientCredentials[] = [
                    'email' => (string) $persistedUser->email,
                    'password' => $resolvedPassword,
                ];
            }
        }

        if ($generatedClientCredentials !== []) {
            $exportPath = $this->exportSeededClientCredentials($generatedClientCredentials);
            $this->command?->warn('Client random passwords generated. Secure handoff file created at: '.$exportPath);
        }

        $this->command?->info('UserSeeder synchronized from current database snapshot.');
    }

    /**
     * @param  array<int, array{email: string, password: string}>  $credentials
     */
    private function exportSeededClientCredentials(array $credentials): string
    {
        $disk = (string) config('helpdesk.seed_client_credentials_disk', 'local');
        $basePath = trim((string) config('helpdesk.seed_client_credentials_path', 'private/seeded-client-credentials'), '/');
        $timestamp = now()->format('Ymd_His');
        $suffix = strtolower(Str::random(6));
        $exportPath = "{$basePath}/client-passwords-{$timestamp}-{$suffix}.csv";

        $rows = ['email,password'];
        foreach ($credentials as $credential) {
            $rows[] = $credential['email'].','.$credential['password'];
        }

        Storage::disk($disk)->put($exportPath, implode(PHP_EOL, $rows).PHP_EOL);

        return $exportPath;
    }
}
