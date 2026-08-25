<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Icons come from the Heroicons package, so a typo in an icon name throws at
 * render time rather than silently drawing nothing. These pages assert the
 * icons genuinely reach the browser.
 */
class IconRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Dela Cruz, Juan M.',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'department' => 'CAS',
            'college_id' => \App\Models\College::firstOrCreate(
                ['code' => 'CAS'], ['name' => 'College of Arts and Sciences'])->id,
            'program' => 'Bachelor of Science in Information Technology',
            'position' => 'Instructor I',
        ]);

        LeaveBalance::create(['user_id' => $user->id, 'vl_balance' => 5, 'sl_balance' => 5]);

        return $user;
    }

    public function test_dashboards_render_real_icon_components(): void
    {
        foreach ([['admin', 'admin.dashboard'], ['employee', 'employee.dashboard']] as [$role, $route]) {
            $html = $this->actingAs($this->user($role))->get(route($route))->assertOk()->getContent();

            // The package stamps every icon it renders.
            $count = substr_count($html, 'data-slot="icon"');

            $this->assertGreaterThan(8, $count,
                "{$route} rendered only {$count} icon components; the page should be iconified.");

            // An unresolved component would leak its own tag into the output.
            $this->assertStringNotContainsString('x-heroicon', $html,
                "{$route} leaked an unrendered icon component.");
        }
    }

    public function test_sidebar_navigation_is_iconified_for_every_role(): void
    {
        foreach ([
            ['admin', 'admin.dashboard'],
            ['dean', 'admin.leave.review.index'],
            ['campus_director', 'admin.leave.review.index'],
            ['employee', 'employee.dashboard'],
        ] as [$role, $route]) {
            $html = $this->actingAs($this->user($role))->get(route($route))->assertOk()->getContent();

            // Every nav row carries an icon, plus the log-out row.
            $navRows = substr_count($html, 'class="nav-link');

            $this->assertGreaterThanOrEqual(3, $navRows,
                "The {$role} sidebar rendered only {$navRows} navigation rows.");
            $this->assertStringContainsString('data-slot="icon"', $html);
        }
    }

    public function test_the_flat_visual_language_has_no_decorative_leftovers(): void
    {
        $css = file_get_contents(
            collect(glob(public_path('build/assets/app-*.css')))->sortByDesc(fn ($f) => filemtime($f))->first()
        );

        // These were the "AI-looking" decorations: brand gradients, a paper
        // texture, serif display type and ornamental rules.
        foreach ([
            'from-maroon' => 'maroon gradient',
            'radial-gradient' => 'paper texture',
            'Source Serif' => 'serif display face',
            'rule-accent' => 'ornamental rule',
        ] as $needle => $what) {
            $this->assertStringNotContainsString($needle, $css,
                "The compiled stylesheet still carries the {$what}.");
        }

        // And the flat primitives that replaced them are present.
        foreach (['.btn-primary', '.nav-link-active', '.card-header', '.table', '.crumbs'] as $primitive) {
            $this->assertStringContainsString($primitive, $css);
        }
    }
}
