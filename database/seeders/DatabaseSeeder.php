<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            TicketSeeder::class,
        ]);
    }
}