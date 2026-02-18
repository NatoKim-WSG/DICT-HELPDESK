<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Hardware',
                'description' => 'Problems with computer hardware, peripherals, and equipment',
                'color' => '#EF4444',
                'is_active' => true,
            ],
            [
                'name' => 'Software',
                'description' => 'Software installation, updates, and application problems',
                'color' => '#3B82F6',
                'is_active' => true,
            ],
            [
                'name' => 'Network & Connectivity',
                'description' => 'Internet, WiFi, VPN and network related issues',
                'color' => '#10B981',
                'is_active' => true,
            ],
            [
                'name' => 'Email & Communication',
                'description' => 'Email setup, calendar, and communication tools',
                'color' => '#F59E0B',
                'is_active' => true,
            ],
            [
                'name' => 'Account & Access',
                'description' => 'Login issues, password resets, and account permissions',
                'color' => '#8B5CF6',
                'is_active' => true,
            ],
            [
                'name' => 'Printer & Scanning',
                'description' => 'Printer setup, printing issues, and scanning problems',
                'color' => '#EC4899',
                'is_active' => true,
            ],
            [
                'name' => 'Mobile Device',
                'description' => 'Smartphone, tablet, and mobile app support',
                'color' => '#06B6D4',
                'is_active' => true,
            ],
            [
                'name' => 'Training & Documentation',
                'description' => 'Training requests and documentation needs',
                'color' => '#84CC16',
                'is_active' => true,
            ],
            [
                'name' => 'Security',
                'description' => 'Security concerns, virus removal, and data protection',
                'color' => '#DC2626',
                'is_active' => true,
            ],
            [
                'name' => 'Other',
                'description' => 'General inquiries and other support requests',
                'color' => '#6B7280',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
