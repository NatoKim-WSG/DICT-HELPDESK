<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        $timestamp = now();
        $departments = [
            ['name' => 'iOne', 'slug' => 'ione', 'logo_path' => 'images/iOne Logo.png'],
            ['name' => 'BOC', 'slug' => 'boc', 'logo_path' => 'images/BOC Logo.png'],
            ['name' => 'DSWD', 'slug' => 'dswd', 'logo_path' => 'images/DSWD Logo.png'],
            ['name' => 'DEPED', 'slug' => 'deped', 'logo_path' => 'images/DEPED Logo.png'],
            ['name' => 'PCG', 'slug' => 'pcg', 'logo_path' => 'images/PCG Logo.png'],
            ['name' => 'NAVY', 'slug' => 'navy', 'logo_path' => 'images/Navy Logo.png'],
            ['name' => 'DA', 'slug' => 'da', 'logo_path' => 'images/DA Logo.png'],
            ['name' => 'DAR', 'slug' => 'dar', 'logo_path' => 'images/DAR Logo.png'],
            ['name' => 'COMELEC', 'slug' => 'comelec', 'logo_path' => 'images/COMELEC Logo.png'],
            ['name' => 'AFP', 'slug' => 'afp', 'logo_path' => 'images/AFP Logo.png'],
            ['name' => 'LGU Pasig', 'slug' => 'lgu-pasig', 'logo_path' => 'images/LGUP Logo.png'],
            ['name' => 'DICT', 'slug' => 'dict', 'logo_path' => 'images/DICT Logo.png'],
            ['name' => 'Others', 'slug' => 'others', 'logo_path' => 'images/Others Logo.png'],
        ];

        foreach ($departments as &$department) {
            $department['created_at'] = $timestamp;
            $department['updated_at'] = $timestamp;
        }

        DB::table('departments')->insert($departments);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
