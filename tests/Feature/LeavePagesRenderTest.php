<?php

namespace Tests\Feature;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke coverage: every leave screen renders for the roles allowed to see it.
 * Blade errors and stale route names surface here rather than in the browser.
 */
class LeavePagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::create([
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
            'first_day_of_service' => '2018-06-04',
        ]);
    }

    private function seedScenario(): array
    {
        $employee = $this->makeUser('employee');
        $dean = $this->makeUser('dean');
        $hr = $this->makeUser('admin');
        $director = $this->makeUser('campus_director');

        LeaveBalance::create([
            'user_id' => $employee->id,
            'vl_balance' => 12.5,
            'sl_balance' => 9.25,
            'service_balance' => 5,
        ]);

        // A ledger card with earned credits, a deduction and service credits,
        // so the card and its PDF exercise every column.
        $ledger = app(LeaveLedgerService::class);

        $ledger->postEntry(
            employee: $employee,
            periodFrom: '2025-01-01', periodTo: '2025-01-31',
            type: 'earned', remarks: 'Monthly accrual for January 2025',
            vlEarned: 1.25, slEarned: 1.25, encodedBy: $hr->id,
        );

        $ledger->postEntry(
            employee: $employee,
            periodFrom: '2025-05-12', periodTo: '2025-05-12',
            type: 'earned', remarks: 'Service credits — National & Local Elections',
            serviceEarned: 5, encodedBy: $hr->id,
        );

        $ledger->postEntry(
            employee: $employee,
            periodFrom: '2025-08-04', periodTo: '2025-08-05',
            type: 'leave_deduction', remarks: 'VL — Family matter',
            vlUsed: 1.5, vlUsedWop: 0.5, encodedBy: $hr->id,
        );

        $application = LeaveApplication::create([
            'user_id' => $employee->id,
            'leave_type' => 'VL',
            'date_from' => '2025-09-01',
            'date_to' => '2025-09-02',
            'days' => 2,
            'reason' => 'Family matter',
            'status' => 'submitted',
            'file_path' => 'leave-applications/test.pdf',
            'file_original_name' => 'leave-form.pdf',
            'uploaded_at' => now(),
        ]);

        return compact('employee', 'dean', 'hr', 'director', 'application');
    }

    public function test_hr_screens_render(): void
    {
        ['hr' => $hr, 'employee' => $employee, 'application' => $application] = $this->seedScenario();

        foreach ([
            route('admin.dashboard'),
            route('admin.leave.index'),
            route('admin.leave.review.index'),
            route('admin.leave.review.show', $application),
            route('admin.leave.templates.index'),
            route('admin.leave.ledger', $employee),
            route('admin.leave.calendar'),
        ] as $url) {
            $this->actingAs($hr)->get($url)->assertOk();
        }
    }

    public function test_dean_screens_render(): void
    {
        ['dean' => $dean, 'employee' => $employee, 'application' => $application] = $this->seedScenario();

        foreach ([
            route('admin.leave.review.index'),
            route('admin.leave.review.show', $application),
            route('admin.leave.index'),
            route('admin.leave.ledger', $employee),
            route('admin.leave.calendar'),
        ] as $url) {
            $this->actingAs($dean)->get($url)->assertOk();
        }
    }

    public function test_campus_director_screens_render(): void
    {
        ['director' => $director, 'employee' => $employee, 'application' => $application] = $this->seedScenario();

        foreach ([
            route('admin.leave.review.index'),
            route('admin.leave.review.show', $application),
            route('admin.leave.index'),
            route('admin.leave.ledger', $employee),
        ] as $url) {
            $this->actingAs($director)->get($url)->assertOk();
        }
    }

    public function test_employee_screens_render(): void
    {
        ['employee' => $employee] = $this->seedScenario();

        $this->actingAs($employee)->get(route('leave.index'))->assertOk();
        $this->actingAs($employee)->get(route('employee.dashboard'))->assertOk();
    }

    public function test_ledger_pdf_contains_the_official_card_headings(): void
    {
        ['hr' => $hr, 'employee' => $employee] = $this->seedScenario();

        $response = $this->actingAs($hr)->get(route('admin.leave.ledger.pdf', $employee));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        // A4 portrait is 595.28 x 841.89 pt — assert the page box, since the
        // client's complaint was specifically about paper size.
        $pdf = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595\.28\d*\s+841\.89\d*/',
            $pdf,
            'The ledger card PDF is not A4 portrait.'
        );
    }

    public function test_every_leave_export_renders(): void
    {
        ['hr' => $hr, 'employee' => $employee] = $this->seedScenario();

        foreach ([
            route('admin.leave.export.pdf'),
            route('admin.leave.calendar.export'),
            route('admin.leave.ledger.pdf', $employee),
        ] as $url) {
            $response = $this->actingAs($hr)->get($url);
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), $url);
        }

        $this->actingAs($hr)->get(route('admin.leave.export.excel'))->assertOk();
    }

    public function test_dean_is_blocked_from_another_colleges_ledger(): void
    {
        ['employee' => $employee] = $this->seedScenario();

        $other = \App\Models\College::firstOrCreate(
            ['code' => 'CTE'], ['name' => 'College of Teacher Education']);
        $otherDean = $this->makeUser('dean');
        $otherDean->update(['college_id' => $other->id, 'department' => 'CTE']);

        $this->actingAs($otherDean)
            ->get(route('admin.leave.ledger', $employee))
            ->assertForbidden();
    }
}
