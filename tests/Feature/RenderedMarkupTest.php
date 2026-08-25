<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checks the HTML that actually reaches the browser, rather than the Blade
 * sources — the bulk class rewrite could in principle have produced markup
 * that compiles but renders wrong.
 */
class RenderedMarkupTest extends TestCase
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

    public static function pageProvider(): array
    {
        return [
            'HR dashboard' => ['admin', 'admin.dashboard'],
            'HR employees' => ['admin', 'admin.employees.index'],
            'HR add employee' => ['admin', 'admin.employees.create'],
            'HR ledger cards' => ['admin', 'admin.leave.index'],
            'HR templates' => ['admin', 'admin.leave.templates.index'],
            'HR review queue' => ['admin', 'admin.leave.review.index'],
            'HR PDS list' => ['admin', 'admin.pds.index'],
            'HR policies' => ['admin', 'admin.policies.index'],
            'Employee leave' => ['employee', 'leave.index'],
            'Employee policies' => ['employee', 'policies.index'],
            'Employee dashboard' => ['employee', 'employee.dashboard'],
            'Employee announcements' => ['employee', 'announcements.index'],
            'Employee notifications' => ['employee', 'notifications.index'],
            'Employee digital ID' => ['employee', 'my-id.show'],

            // The Dean and Campus Director have their own dashboards and a
            // college-scoped calendar, so their chrome is covered too.
            'Dean dashboard' => ['dean', 'admin.dashboard'],
            'Dean review queue' => ['dean', 'admin.leave.review.index'],
            'Dean ledgers' => ['dean', 'admin.leave.index'],
            'Dean calendar' => ['dean', 'admin.leave.calendar'],
            'Director dashboard' => ['campus_director', 'admin.dashboard'],
            'Director review queue' => ['campus_director', 'admin.leave.review.index'],
            'Director calendar' => ['campus_director', 'admin.leave.calendar'],

            'HR colleges' => ['admin', 'admin.colleges.index'],
            'HR announcements' => ['admin', 'admin.announcements.index'],
            'HR audit trail' => ['admin', 'admin.activity-logs.index'],
            'HR calendar' => ['admin', 'admin.leave.calendar'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageProvider')]
    public function test_page_renders_valid_design_system_markup(string $role, string $route): void
    {
        $html = $this->actingAs($this->user($role))->get(route($route))->assertOk()->getContent();

        // The stylesheet must be wired up, or every design-system class on the
        // page is inert. @vite emits the built bundle normally and the dev
        // server client while `npm run dev` is running, so accept either.
        $this->assertTrue(
            (bool) preg_match('/<link[^>]+build\/assets\/app-[^"]+\.css/', $html)
            || str_contains($html, '@vite/client'),
            "{$route}: no stylesheet is wired up — neither the built bundle nor the Vite dev client."
        );

        $this->assertStringNotContainsString('class=""', $html,
            "{$route}: rendered an empty class attribute.");

        // A leaked Blade directive means a broken component invocation.
        foreach (['@php', '@endphp', '@class(', '{{ $', '<x-'] as $leak) {
            $this->assertStringNotContainsString($leak, $html,
                "{$route}: leaked an unrendered Blade fragment ({$leak}).");
        }

        // No page should still be reaching for the retired palette.
        $this->assertDoesNotMatchRegularExpression('/\bclass="[^"]*\b(?:bg|text|border)-gray-\d/', $html,
            "{$route}: still renders gray-* utilities.");
    }

    /**
     * The page heading must appear exactly once in the chrome.
     *
     * It has regressed twice: first as two <h1> tags, then as an <h1> plus a
     * breadcrumb leaf carrying the same words. Asserting on the tag alone
     * missed the second case, so this compares the rendered text.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pageProvider')]
    public function test_page_heading_is_not_duplicated(string $role, string $route): void
    {
        $html = $this->actingAs($this->user($role))->get(route($route))->assertOk()->getContent();

        preg_match_all('/<h1[^>]*class="[^"]*page-title[^"]*"[^>]*>(.*?)<\/h1>/s', $html, $m);

        $this->assertCount(1, $m[0],
            "{$route} rendered " . count($m[0]) . ' page titles; there must be exactly one.');

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html),
            "{$route} renders more than one <h1>.");

        $heading = trim(html_entity_decode(strip_tags($m[1][0])));
        $this->assertNotSame('', $heading, "{$route} rendered an empty page title.");

        // The application bar's title slot must not echo the same words back.
        // Only that slot is inspected: the bar also carries the notification
        // bell, whose dropdown legitimately says "Notifications".
        preg_match('/<div data-app-bar-title[^>]*>(.*?)<\/div>/s', $html, $slot);

        if ($slot) {
            $bar = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($slot[1]))));

            $this->assertStringNotContainsString($heading, $bar,
                "{$route} repeats the page heading in the application bar.");
        }
    }

    public function test_every_role_renders_its_own_sidebar(): void
    {
        $expectations = [
            'admin' => ['Ledger Cards', 'Form Templates', 'PDS Requests'],
            // A Dean approves leave but does not hold other people's credit
            // balances, so no Employee Ledgers link for them.
            'dean' => ['Awaiting My Review', 'Leave Calendar', 'My Leave Ledger'],
            'campus_director' => ['Awaiting My Review', 'Employee Ledgers'],
            'employee' => ['Personal Data Sheet', 'My Leave', 'My Digital ID'],
        ];

        foreach ($expectations as $role => $links) {
            $user = $this->user($role);
            $route = $role === 'employee' ? 'employee.dashboard'
                : ($role === 'admin' ? 'admin.dashboard' : 'admin.leave.review.index');

            $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();

            foreach ($links as $link) {
                $this->assertStringContainsString($link, $html,
                    "The {$role} sidebar is missing “{$link}”.");
            }

            $this->assertStringContainsString($user->roleLabel(), $html,
                "The {$role} header does not show its role badge.");
        }
    }

    public function test_login_page_renders_on_the_design_system(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('class="input', $html);
        $this->assertStringContainsString('btn-primary', $html);
        $this->assertStringContainsString('class="label"', $html);
    }
}
