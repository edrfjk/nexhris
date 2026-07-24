<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'employee_number' => 'ADMIN-001',
            'name' => 'HR Administrator',
            'email' => 'admin@ispsc.edu.ph',
            'position' => 'HR Administrator',
            'department' => 'HR Office',
            'role' => 'admin',
            'status' => 'active',
            'password' => Hash::make('ChangeMe123!'),
        ]);
    }
}