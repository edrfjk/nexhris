<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The HR-side screens all read the same organisation helpers now, so these
 * cover the shared contract: every page renders, the college/department
 * filters actually narrow the list, and nobody's affiliation prints as a
 * bare college code.
 */
class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private College $cas;
    private Department $bsit;
    private Department $bssw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->cas = College::where('code', 'CAS')->firstOrFail();

        // The seeder already backfills the real programmes, so reuse them.
        $this->bsit = Department::firstOrCreate(
            ['college_id' => $this->cas->id, 'code' => 'BSIT'],
            ['name' => 'Bachelor of Science in Information Technology'],
        );
        $this->bssw = Department::firstOrCreate(
            ['college_id' => $this->cas->id, 'code' => 'BSSW'],
            ['name' => 'Bachelor of Science in Social Work'],
        );
    }

    private function employee(string $name, ?Department $department, string $role = 'employee'): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role,
            'status' => 'active',
            'college_id' => $this->cas->id,
            'department_id' => $department?->id,
            'department' => $this->cas->code,
            'program' => $department?->name,
        ]);
    }

    public function test_every_hr_screen_renders(): void
    {
        $employee = $this->employee('Render Check', $this->bsit);

        $pages = [
            route('admin.dashboard'),
            route('admin.employees.index'),
            route('admin.employees.show', $employee),
            route('admin.colleges.index'),
            route('admin.pds.index'),
            route('admin.pds.show', $employee),
            route('admin.leave.index'),
            route('admin.leave.ledger', $employee),
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->hr)->get($url)->assertOk();
        }
    }

    public function test_the_pds_queue_can_be_narrowed_to_one_department(): void
    {
        $this->employee('Ines In BSIT', $this->bsit);
        $this->employee('Rico In BSSW', $this->bssw);

        $this->actingAs($this->hr)
            ->get(route('admin.pds.index', ['department' => $this->bsit->id]))
            ->assertOk()
            ->assertSee('Ines In BSIT')
            ->assertDontSee('Rico In BSSW');
    }

    public function test_the_pds_compliance_counts_follow_the_filter(): void
    {
        $cte = College::where('code', 'CTE')->firstOrFail();

        $this->employee('Only CAS Person', $this->bsit);
        User::factory()->create([
            'name' => 'CTE Person', 'role' => 'employee', 'status' => 'active',
            'college_id' => $cte->id,
        ]);

        // Filtered to CAS, the denominator must be the CAS headcount alone —
        // otherwise the percentages on the stat cards are meaningless.
        $response = $this->actingAs($this->hr)
            ->get(route('admin.pds.index', ['college' => $this->cas->id]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('totalEmployees'));
    }

    public function test_the_ledger_list_includes_deans_and_the_campus_director(): void
    {
        $this->employee('Dean Person', $this->bsit, 'dean');
        $this->employee('Director Person', null, 'campus_director');

        // They accrue and spend leave like anyone else, so HR must be able to
        // reach their ledger cards from this list.
        $this->actingAs($this->hr)
            ->get(route('admin.leave.index'))
            ->assertOk()
            ->assertSee('Dean Person')
            ->assertSee('Director Person');
    }

    public function test_the_ledger_list_can_be_narrowed_to_one_department(): void
    {
        $this->employee('Ledger BSIT', $this->bsit);
        $this->employee('Ledger BSSW', $this->bssw);

        $this->actingAs($this->hr)
            ->get(route('admin.leave.index', ['department' => $this->bsit->id]))
            ->assertOk()
            ->assertSee('Ledger BSIT')
            ->assertDontSee('Ledger BSSW');
    }

    public function test_affiliation_prints_as_names_not_a_bare_college_code(): void
    {
        $employee = $this->employee('Named Org', $this->bsit);

        $this->actingAs($this->hr)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertSee('College of Arts and Sciences')
            ->assertSee('Bachelor of Science in Information Technology');
    }

    public function test_changing_a_password_does_not_disturb_the_org_fields(): void
    {
        $employee = $this->employee('Password Only', $this->bsit);

        // The Security tab posts a reduced form. It must not blank out the
        // college or department just because it does not carry them.
        $this->actingAs($this->hr)->put(route('admin.employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect();

        $employee->refresh();

        $this->assertSame($this->cas->id, $employee->college_id);
        $this->assertSame($this->bsit->id, $employee->department_id);
        $this->assertSame('Bachelor of Science in Information Technology', $employee->program);
    }

    public function test_the_templates_screen_publishes_the_forms_hr_uploads(): void
    {
        // The PDS and the leave form are uploaded by HR and can be revised;
        // both used to be managed on separate screens.
        $this->actingAs($this->hr)
            ->get(route('admin.leave.templates.index'))
            ->assertOk()
            ->assertSee('Personal Data Sheet')
            ->assertSee('Leave Form')
            ->assertSee(route('admin.pds.templates.store'))
            ->assertSee(route('admin.leave.templates.store'));
    }

    public function test_the_ledger_card_is_not_something_hr_uploads(): void
    {
        // Its layout is fixed and built into the system, so there is nothing
        // to publish and no way to publish a wrong one.
        $this->actingAs($this->hr)
            ->get(route('admin.leave.templates.index'))
            ->assertOk()
            ->assertDontSee('Master Ledger');
    }

    public function test_the_pds_page_no_longer_carries_its_own_upload_form(): void
    {
        $this->actingAs($this->hr)
            ->get(route('admin.pds.index'))
            ->assertOk()
            ->assertDontSee(route('admin.pds.templates.store'))
            ->assertSee(route('admin.leave.templates.index', ['tab' => 'pds']));
    }

    public function test_the_review_queue_puts_the_longest_waiting_form_first(): void
    {
        $employee = $this->employee('Queue Person', $this->bsit);

        $old = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'VL',
            'date_from' => now()->addWeek(), 'date_to' => now()->addWeek(),
            'days' => 1, 'status' => 'dean_approved',
            'uploaded_at' => now()->subDays(25),
            'dean_reviewed_at' => now()->subDays(20),
        ]);
        $old->forceFill(['created_at' => now()->subDays(25)])->save();

        $fresh = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'SL',
            'date_from' => now()->addWeek(), 'date_to' => now()->addWeek(),
            'days' => 1, 'status' => 'dean_approved',
            'uploaded_at' => now(),
            'dean_reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->hr)
            ->get(route('admin.leave.review.index'))
            ->assertOk();

        // An approval queue is worked oldest-first, so the form that has been
        // waiting three weeks must not sit below one filed this morning.
        $ordered = $response->viewData('applications')->pluck('id')->all();
        $this->assertSame([$old->id, $fresh->id], $ordered);

        $response->assertSee('20 days');
    }

    public function test_waiting_time_counts_from_the_current_stage_not_the_upload(): void
    {
        $employee = $this->employee('Staged Person', $this->bsit);

        $application = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'VL',
            'date_from' => now()->addWeek(), 'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'dean_approved',
            'uploaded_at' => now()->subDays(30),
            'dean_reviewed_at' => now()->subDays(3),
        ]);

        // It has been on HR's desk for three days, not thirty.
        $this->assertSame(3, $application->daysWaiting());
    }

    public function test_a_skipped_stage_does_not_reset_the_waiting_clock(): void
    {
        $employee = $this->employee('Skipped Stage', $this->bsit);

        // A Dean does not sign their own leave, so dean_reviewed_at stays null.
        $application = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'VL',
            'date_from' => now()->addWeek(), 'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'dean_approved',
            'uploaded_at' => now()->subDays(6),
            'dean_reviewed_at' => null,
        ]);

        $this->assertSame(6, $application->daysWaiting());
    }

    public function test_the_review_queue_can_be_narrowed_to_one_college(): void
    {
        $cte = College::where('code', 'CTE')->firstOrFail();

        $cas = $this->employee('Cas Applicant', $this->bsit);
        $other = User::factory()->create([
            'name' => 'Cte Applicant', 'role' => 'employee', 'status' => 'active',
            'college_id' => $cte->id,
        ]);

        foreach ([$cas, $other] as $person) {
            LeaveApplication::create([
                'user_id' => $person->id, 'leave_type' => 'VL',
                'date_from' => now()->addWeek(), 'date_to' => now()->addWeek(),
                'days' => 1, 'status' => 'dean_approved',
                'uploaded_at' => now(), 'dean_reviewed_at' => now(),
            ]);
        }

        $this->actingAs($this->hr)
            ->get(route('admin.leave.review.index', ['college' => $this->cas->id]))
            ->assertOk()
            ->assertSee('Cas Applicant')
            ->assertDontSee('Cte Applicant');
    }

    public function test_the_dashboard_names_people_whose_leave_cannot_route(): void
    {
        $stranded = User::factory()->create([
            'name' => 'Unrouted Person', 'role' => 'employee', 'status' => 'active',
            'college_id' => null,
        ]);
        $this->employee('Routed Person', $this->bsit);

        // No college means no Dean, and the form would stall with no error.
        $response = $this->actingAs($this->hr)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('no college assigned')
            ->assertSee('Unrouted Person');

        $ids = $response->viewData('data')['unrouted']->pluck('id')->all();
        $this->assertSame([$stranded->id], $ids);
    }

    public function test_the_dashboard_stays_quiet_when_everyone_is_routed(): void
    {
        $this->employee('Routed Person', $this->bsit);

        $this->actingAs($this->hr)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('no college assigned');
    }
}
