<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar follows one order for every role: what is waiting on you, then
 * what you manage, then your own records, then shared resources, then your
 * account. These pin that arrangement so it does not drift back.
 */
class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function sidebarOf(string $role): string
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        $home = $role === 'employee' ? route('employee.dashboard') : route('admin.dashboard');

        $html = $this->actingAs($user)->get($home)->assertOk()->getContent();

        // Only the nav, so headings elsewhere on the page cannot be mistaken
        // for section labels.
        $start = strpos($html, '<nav');
        $end = strpos($html, '</nav>', $start);

        return substr($html, $start, $end - $start);
    }

    /** The order labels actually appear in, top to bottom. */
    private function sectionOrder(string $nav): array
    {
        preg_match_all('/nav-section-label[^>]*>\s*([^<]+?)\s*</', $nav, $matches);

        if (empty($matches[1])) {
            // Fall back to whatever wrapper the component uses.
            preg_match_all('/<p[^>]*>\s*([A-Z][A-Za-z\s&;]+?)\s*<\/p>/', $nav, $matches);
        }

        return array_map('trim', $matches[1] ?? []);
    }

    public static function everyRole(): array
    {
        return [
            'employee' => ['employee'],
            'dean' => ['dean'],
            'campus director' => ['campus_director'],
            'hr administrator' => ['admin'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('everyRole')]
    public function test_the_dashboard_is_the_first_link_for_every_role(string $role): void
    {
        $nav = $this->sidebarOf($role);

        $this->assertMatchesRegularExpression('/Dashboard/', $nav);

        // Nothing may sit above the dashboard.
        $firstItem = strpos($nav, 'Dashboard');
        foreach (['My Profile', 'HR Policies', 'Announcements'] as $later) {
            $this->assertGreaterThan($firstItem, strpos($nav, $later),
                "{$role}: {$later} appears above the dashboard.");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('everyRole')]
    public function test_account_links_sit_at_the_bottom_for_every_role(string $role): void
    {
        $nav = $this->sidebarOf($role);

        // My Profile used to sit under "System" next to the audit log for HR,
        // and under "Resources" beside the policies for everyone else.
        $profile = strpos($nav, 'My Profile');
        $this->assertNotFalse($profile, "{$role}: no My Profile link.");

        foreach (['Announcements', 'HR Policies'] as $earlier) {
            $this->assertLessThan($profile, strpos($nav, $earlier),
                "{$role}: {$earlier} should sit above My Profile.");
        }
    }

    public static function staffRoles(): array
    {
        return [
            'employee' => ['employee'],
            'dean' => ['dean'],
            'campus director' => ['campus_director'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staffRoles')]
    public function test_staff_can_reach_their_own_records(string $role): void
    {
        $nav = $this->sidebarOf($role);

        // Employees, Deans and the Campus Director all file leave and a PDS.
        foreach ([
            route('leave.index'),
            route('leave.ledger.mine'),
            route('pds.edit'),
            route('my-id.show'),
            route('profile.edit'),
        ] as $url) {
            $this->assertStringContainsString($url, $nav,
                "{$role}: the sidebar does not link {$url}.");
        }
    }

    public function test_hr_is_a_system_account_with_no_personal_records(): void
    {
        $nav = $this->sidebarOf('admin');

        // The HR Administrator maintains everyone else's records; it is not a
        // member of staff and does not keep leave, a ledger or a PDS of its own.
        foreach ([
            route('leave.index'),
            route('leave.ledger.mine'),
            route('pds.edit'),
            route('my-id.show'),
        ] as $url) {
            $this->assertStringNotContainsString($url, $nav,
                "The HR sidebar offers {$url}, which belongs to a staff account.");
        }

        // They still need their own password and contact details.
        $this->assertStringContainsString(route('profile.edit'), $nav);
    }

    public function test_everything_about_leave_sits_in_one_hr_section(): void
    {
        $nav = $this->sidebarOf('admin');

        $this->assertStringContainsString('Leave Management', $nav);

        // Reviews, ledger cards and the calendar belong together, in that
        // order, under the one heading.
        $reviews = strpos($nav, 'Leave Reviews');
        $cards = strpos($nav, 'Ledger Cards');
        $calendar = strpos($nav, 'Leave Calendar');
        $section = strpos($nav, 'Leave Management');

        $this->assertLessThan($reviews, $section);
        $this->assertLessThan($cards, $reviews);
        $this->assertLessThan($calendar, $cards);
    }

    public function test_the_hr_sections_read_formally(): void
    {
        $nav = $this->sidebarOf('admin');

        foreach (['Overview', 'People', 'Leave Management', 'Records', 'Publishing', 'System'] as $label) {
            $this->assertStringContainsString($label, $nav, "Missing the {$label} section.");
        }

        $this->assertStringNotContainsString('Needs Review', $nav);
    }

    public function test_publishing_links_are_grouped_together_for_hr(): void
    {
        $nav = $this->sidebarOf('admin');

        // Announcements were under "System" and policies under "Records".
        $announcements = strpos($nav, 'Announcements');
        $policies = strpos($nav, 'HR Policies');
        $templates = strpos($nav, 'Form Templates');
        $activityLog = strpos($nav, 'Activity Log');

        $this->assertLessThan($activityLog, $announcements);
        $this->assertLessThan($activityLog, $policies);
        $this->assertLessThan($activityLog, $templates);
    }

    public function test_a_plain_employee_is_offered_no_admin_links(): void
    {
        $nav = $this->sidebarOf('employee');

        foreach (['Employee Accounts', 'Activity Log', 'Form Templates', 'Ledger Cards'] as $adminOnly) {
            $this->assertStringNotContainsString($adminOnly, $nav,
                "An employee is offered the {$adminOnly} link.");
        }
    }

    public function test_a_dean_is_not_offered_other_peoples_ledger_cards(): void
    {
        $nav = $this->sidebarOf('dean');

        // Ledger cards are HR's record of everyone's credits. A Dean approves
        // leave for their college but does not hold those balances.
        $this->assertStringNotContainsString('Employee Ledgers', $nav);

        // Matched as a complete href: the calendar's URL contains the ledger
        // list's as a prefix, so a substring check passes either way.
        $this->assertStringNotContainsString('href="' . route('admin.leave.index') . '"', $nav);

        // Their own card stays reachable.
        $this->assertStringContainsString(route('leave.ledger.mine'), $nav);
    }

    public function test_the_campus_director_keeps_campus_wide_ledger_access(): void
    {
        $nav = $this->sidebarOf('campus_director');

        $this->assertStringContainsString('Employee Ledgers', $nav);
    }
}
