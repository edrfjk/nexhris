<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Campus Director gives final sign-off for everyone else, and their own
 * leave stops at HR because no role sits above them — the client's decision.
 */
class CampusDirectorTest extends TestCase
{
    use RefreshDatabase;

    private User $director;
    private User $hr;
    private College $cas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->director = User::factory()->create([
            'name' => 'Director Cruz', 'role' => 'campus_director', 'status' => 'active',
        ]);
        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->cas = College::where('code', 'CAS')->firstOrFail();
    }

    private function application(User $applicant, array $attributes = []): LeaveApplication
    {
        return LeaveApplication::create(array_merge([
            'user_id' => $applicant->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'hr_approved',
            'uploaded_at' => now()->subDays(2),
            'dean_reviewed_at' => now()->subDay(),
            'hr_reviewed_at' => now(),
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // Navigation — the gap that made their own records unreachable
    // ------------------------------------------------------------------

    public function test_the_director_can_reach_their_own_records_from_the_sidebar(): void
    {
        $html = $this->actingAs($this->director)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // They file leave like anyone else, so these must be linked, not just
        // reachable by typing the URL.
        foreach ([
            route('admin.dashboard'),
            route('leave.index'),
            route('leave.ledger.mine'),
            route('my-id.show'),
            route('pds.edit'),
            route('profile.edit'),
        ] as $url) {
            $this->assertStringContainsString($url, $html,
                "The Campus Director sidebar does not link {$url}.");
        }
    }

    public function test_those_pages_actually_open_for_the_director(): void
    {
        foreach ([
            'admin.dashboard', 'leave.index', 'leave.ledger.mine',
            'my-id.show', 'pds.edit', 'profile.edit',
        ] as $route) {
            $this->actingAs($this->director)->get(route($route))->assertOk();
        }
    }

    // ------------------------------------------------------------------
    // The chain
    // ------------------------------------------------------------------

    public function test_the_directors_own_leave_is_final_at_hr(): void
    {
        $chain = app(LeaveChain::class);

        // HR alone: a Dean reports to the Campus Director, so no Dean signs
        // their form, and nobody outranks them for a final signature.
        $this->assertSame(['hr'], $chain->stagesFor($this->director));
        $this->assertSame('hr', $chain->finalStage($this->director));
    }

    public function test_hr_approving_the_directors_form_completes_it(): void
    {
        $application = $this->application($this->director, [
            'status' => 'dean_approved',
            'hr_reviewed_at' => null,
        ]);

        $this->actingAs($this->hr)
            ->post(route('admin.leave.review.approve', $application), ['remarks' => 'Approved.'])
            ->assertRedirect();

        // It must not stall at hr_approved waiting for a signature that can
        // never come.
        $this->assertSame(LeaveChain::APPROVED, $application->fresh()->status);
        $this->assertTrue($application->fresh()->isFullyApproved());
    }

    public function test_the_director_never_sees_their_own_form_in_their_queue(): void
    {
        $this->application($this->director, ['status' => 'hr_approved']);

        $response = $this->actingAs($this->director)
            ->get(route('admin.leave.review.index'))
            ->assertOk();

        $this->assertSame(0, $response->viewData('applications')->total());
    }

    public function test_the_director_gives_final_approval_for_an_employee(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $this->cas->id,
        ]);
        $application = $this->application($employee);

        $this->actingAs($this->director)
            ->post(route('admin.leave.review.approve', $application), [])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveChain::APPROVED, $application->status);
        $this->assertSame($this->director->id, $application->director_id);
    }

    // ------------------------------------------------------------------
    // Deciding with the facts in front of you
    // ------------------------------------------------------------------

    public function test_the_review_page_warns_when_credits_do_not_cover_the_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $this->cas->id,
        ]);
        LeaveBalance::create([
            'user_id' => $employee->id,
            'vl_balance' => 3, 'sl_balance' => 10, 'service_balance' => 0,
        ]);

        $application = $this->application($employee, ['days' => 8, 'leave_type' => 'VL']);

        // 8 days asked against 3 held. The final approver should be told
        // without opening the ledger and subtracting by hand.
        $this->assertSame(5.0, $application->creditShortfall());

        $this->actingAs($this->director)
            ->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->assertSee('Short by 5 days');
    }

    public function test_the_review_page_confirms_when_credits_do_cover_it(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $this->cas->id,
        ]);
        LeaveBalance::create([
            'user_id' => $employee->id,
            'vl_balance' => 12, 'sl_balance' => 10, 'service_balance' => 0,
        ]);

        $application = $this->application($employee, ['days' => 2]);

        $this->assertFalse($application->exceedsAvailableCredits());

        $this->actingAs($this->director)
            ->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->assertSee('Credits cover this');
    }

    public function test_sick_leave_is_measured_against_the_sick_leave_balance(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $this->cas->id,
        ]);
        LeaveBalance::create([
            'user_id' => $employee->id,
            'vl_balance' => 40, 'sl_balance' => 1, 'service_balance' => 0,
        ]);

        // A large vacation balance must not make a sick leave request look fine.
        $application = $this->application($employee, ['leave_type' => 'SL', 'days' => 5]);

        $this->assertSame(1.0, $application->availableCredits());
        $this->assertSame(4.0, $application->creditShortfall());
    }

    public function test_an_applicant_with_no_balance_record_is_treated_as_having_none(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $this->cas->id,
        ]);

        $application = $this->application($employee, ['days' => 2]);

        $this->assertSame(0.0, $application->availableCredits());
        $this->assertTrue($application->exceedsAvailableCredits());
    }

    // ------------------------------------------------------------------
    // Dashboard
    // ------------------------------------------------------------------

    public function test_the_dashboard_shows_the_longest_waiting_form(): void
    {
        $employee = User::factory()->create([
            'name' => 'Patient Applicant', 'role' => 'employee', 'status' => 'active',
            'college_id' => $this->cas->id,
        ]);

        $this->application($employee, ['hr_reviewed_at' => now()->subDays(11)]);

        $this->actingAs($this->director)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Longest waiting')
            ->assertSee('Patient Applicant')
            ->assertSee('11 days');
    }

    public function test_the_dashboard_surfaces_the_directors_own_application(): void
    {
        $this->application($this->director, [
            'status' => 'dean_approved',
            'hr_reviewed_at' => null,
        ]);

        // It is invisible in their queue by design, so the dashboard is the
        // only place they would see it.
        $this->actingAs($this->director)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Your own leave application')
            ->assertSee('Awaiting HR review');
    }

    public function test_the_dashboard_stays_clean_with_no_own_application(): void
    {
        $this->actingAs($this->director)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Your own leave application');
    }

    // ------------------------------------------------------------------
    // My Leave Ledger — a page, not a file download
    // ------------------------------------------------------------------

    public function test_the_ledger_nav_lands_on_a_page_not_a_pdf(): void
    {
        // It used to answer the nav click with the raw PDF, leaving the
        // viewer with no heading, no sidebar and no way back.
        $response = $this->actingAs($this->director)
            ->get(route('leave.ledger.mine'))
            ->assertOk();

        $this->assertStringContainsString('text/html', $response->headers->get('content-type'));
        $response->assertSee('My Leave Ledger');
    }

    public function test_the_ledger_page_shows_credits_and_the_card(): void
    {
        LeaveBalance::create([
            'user_id' => $this->director->id,
            'vl_balance' => 17.25, 'sl_balance' => 9.5, 'service_balance' => 2,
        ]);

        // The card is drawn from the posted entries in the campus template's
        // layout, so it needs no seeded workbook and is always available.
        $this->actingAs($this->director)
            ->get(route('leave.ledger.mine'))
            ->assertOk()
            ->assertSee('17.25')
            ->assertSee('9.50')
            ->assertSee('Official ledger card')
            ->assertDontSee('not ready yet');
    }

    public function test_the_ledger_page_offers_the_official_card(): void
    {
        // The workbook route is gone; the card is drawn from the posted
        // entries in the campus template's layout.
        $this->actingAs($this->director)
            ->get(route('leave.ledger.mine'))
            ->assertOk()
            ->assertSee(route('leave.ledger.pdf'), false);
    }
}
