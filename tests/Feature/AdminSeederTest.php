<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_seed_without_a_configured_password(): void
    {
        config(['auth.initial_admin.password' => null]);

        $this->expectException(RuntimeException::class);

        try {
            $this->seed(AdminUserSeeder::class);
        } finally {
            $this->assertSame(0, User::where('role', 'admin')->count());
        }
    }

    public function test_it_refuses_a_weak_password(): void
    {
        config(['auth.initial_admin.password' => 'short']);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminUserSeeder::class);
    }

    public function test_it_creates_the_admin_from_configuration(): void
    {
        config([
            'auth.initial_admin.email' => 'hr@example.test',
            'auth.initial_admin.password' => 'Initial2026pass',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'hr@example.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('Initial2026pass', $admin->password));
    }

    public function test_it_never_overwrites_an_existing_admin(): void
    {
        config([
            'auth.initial_admin.email' => 'hr@example.test',
            'auth.initial_admin.password' => 'Initial2026pass',
        ]);
        $this->seed(AdminUserSeeder::class);

        config(['auth.initial_admin.password' => 'Different2026pass']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertTrue(Hash::check('Initial2026pass', User::where('email', 'hr@example.test')->value('password')));
    }

    public function test_the_seeder_contains_no_built_in_password(): void
    {
        $source = file_get_contents(database_path('seeders/AdminUserSeeder.php'));

        $this->assertDoesNotMatchRegularExpression("/Hash::make\\(\\s*['\"]/", $source);
    }
}
