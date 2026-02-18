<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FixSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $plainPassword = env('SEED_SUPER_ADMIN_PASSWORD') ?: Str::random(20);

        // Find or create the super admin user
        $superAdmin = User::where('email', 'admin@ione.com')->first();

        if ($superAdmin) {
            // Update existing user to be super admin
            $superAdmin->update([
                'role' => 'super_admin',
                'name' => 'Super Administrator',
            ]);
            echo "Updated existing user to super admin\n";
        } else {
            // Create new super admin
            User::create([
                'name' => 'Super Administrator',
                'email' => 'admin@ione.com',
                'phone' => '+1234567890',
                'department' => 'Administration',
                'role' => 'super_admin',
                'password' => Hash::make($plainPassword),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            echo "Created new super admin user\n";
            echo "Temporary password: {$plainPassword}\n";
        }

        // Ensure only one super admin exists
        $superAdmins = User::where('role', 'super_admin')->get();
        if ($superAdmins->count() > 1) {
            // Keep the first one, convert others to admin
            $firstSuperAdmin = $superAdmins->first();
            User::where('role', 'super_admin')
                ->where('id', '!=', $firstSuperAdmin->id)
                ->update(['role' => 'admin']);
            echo "Ensured only one super admin exists\n";
        }
    }
}
