<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, convert any existing 'agent' users to 'admin'
        DB::table('users')->where('role', 'agent')->update(['role' => 'admin']);

        // Drop the role column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        // Recreate with only the required roles
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['client', 'admin', 'super_admin'])->default('client');
        });

        // Ensure there's only one super admin
        $superAdmins = DB::table('users')->where('role', 'super_admin')->get();
        if ($superAdmins->count() > 1) {
            // Keep the first super admin, convert others to admin
            $firstSuperAdmin = $superAdmins->first();
            DB::table('users')
                ->where('role', 'super_admin')
                ->where('id', '!=', $firstSuperAdmin->id)
                ->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['client', 'admin', 'agent', 'super_admin'])->default('client');
        });
    }
};
