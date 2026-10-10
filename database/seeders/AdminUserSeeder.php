<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('auth.initial_admin.email');

        // Never reset an administrator who already exists: re-running the
        // seeder on a live server must not hand anyone a known password.
        if (User::where('employee_number', 'ADMIN-001')->orWhere('email', $email)->exists()) {
            $this->command?->info('HR Administrator already exists; left unchanged.');

            return;
        }

        $password = config('auth.initial_admin.password');

        if (! filled($password)) {
            throw new RuntimeException(
                'Set ADMIN_INITIAL_PASSWORD in .env before seeding the first HR Administrator.'
            );
        }

        // The same rule HR accounts are held to everywhere else. The message
        // names the rule only, never the value.
        if (Validator::make(['password' => $password], ['password' => Password::min(8)->letters()->numbers()])->fails()) {
            throw new RuntimeException(
                'ADMIN_INITIAL_PASSWORD must be at least 8 characters with letters and numbers.'
            );
        }

        User::create([
            'employee_number' => 'ADMIN-001',
            'name' => 'HR Administrator',
            'email' => $email,
            'position' => 'HR Administrator',
            'department' => 'HR Office',
            'role' => 'admin',
            'status' => 'active',
            'password' => Hash::make($password),
        ]);

        $this->command?->info("HR Administrator created for {$email}. Change the password after first sign-in.");
    }
}
