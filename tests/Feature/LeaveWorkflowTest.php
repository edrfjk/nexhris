<?php

namespace Tests\Feature;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveFormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => $attributes['name'] ?? ucfirst($role) . ' User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'department' => $attributes['department'] ?? 'CAS',
            'college_id' => $attributes['college_id'] ?? \App\Models\College::firstOrCreate(
                ['code' => 'CAS'], ['name' => 'College of Arts and Sciences'])->id,
            'program' => $attributes['program'] ?? 'Bachelor of Science in Information Technology',
            'position' => $attributes['position'] ?? 'Instructor I',
        ]);
    }

    /**
     * Builds the four actors: the employee filing leave, their Dean (same
     * program), the HR Administrator and the Campus Director.
     */
    private function cast(): array
    {
        $employee = $this->makeUser('employee', ['name' => 'Dela Cruz, Juan M.']);
        $dean = $this->makeUser('dean', ['college_id' => $employee->college_id]);
        $hr = $this->makeUser('admin');
        $director = $this->makeUser('campus_director');

        LeaveBalance::create([
            'user_id' => $employee->id,
            'vl_balance' => 10,
            'sl_balance' => 10,
            'service_balance' => 5,
        ]);

        return [$employee, $dean, $hr, $director];
    }

    private function submitForm(User $employee): LeaveApplication
    {
        Storage::fake('public');

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => now()->addWeek()->format('Y-m-d'),
            'date_to' => now()->addWeek()->addDay()->format('Y-m-d'),
            'reason' => 'Family matter',
            'leave_form' => UploadedFile::fake()->create('leave-form.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        return LeaveApplication::where('user_id', $employee->id)->firstOrFail();
    }

    public function test_uploaded_form_goes_to_the_dean_first(): void
    {
        [$employee] = $this->cast();

        $application = $this->submitForm($employee);

        $this->assertSame('submitted', $application->status);
        $this->assertNotNull($application->file_path);
        $this->assertSame('leave-form.pdf', $application->file_original_name);
        $this->assertSame('dean', $application->currentStage());
    }

    public function test_form_moves_dean_then_hr_then_campus_director(): void
    {
        [$employee, $dean, $hr, $director] = $this->cast();
        $application = $this->submitForm($employee);

        $this->actingAs($dean)
            ->post(route('admin.leave.review.approve', $application))
            ->assertRedirect(route('admin.leave.review.index'));
        $this->assertSame('dean_approved', $application->fresh()->status);

        $this->actingAs($hr)
            ->post(route('admin.leave.review.approve', $application))
            ->assertRedirect();
        $this->assertSame('hr_approved', $application->fresh()->status);

        $this->actingAs($director)
            ->post(route('admin.leave.review.approve', $application))
            ->assertRedirect();

        $application->refresh();
        $this->assertSame('cd_approved', $application->status);
        $this->assertTrue($application->isFullyApproved());
        $this->assertCount(3, $application->approvals);
    }

    public function test_reviewers_cannot_act_out_of_turn(): void
    {
        [$employee, $dean, $hr, $director] = $this->cast();
        $application = $this->submitForm($employee);

        // Still with the Dean, so HR and the Campus Director must be refused.
        $this->actingAs($hr)
            ->post(route('admin.leave.review.approve', $application))
            ->assertForbidden();

        $this->actingAs($director)
            ->post(route('admin.leave.review.approve', $application))
            ->assertForbidden();

        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_a_dean_only_sees_their_own_colleges_employees(): void
    {
        [$employee] = $this->cast();
        $application = $this->submitForm($employee);

        // The migration seeds the configured colleges, so reuse rather than insert.
        $otherCollege = \App\Models\College::firstOrCreate(
            ['code' => 'CTE'], ['name' => 'College of Teacher Education']);
        $otherDean = $this->makeUser('dean', ['college_id' => $otherCollege->id, 'department' => 'CTE']);

        $this->actingAs($otherDean)
            ->get(route('admin.leave.review.index'))
            ->assertOk()
            ->assertDontSee($employee->name);

        $this->actingAs($otherDean)
            ->post(route('admin.leave.review.approve', $application))
            ->assertForbidden();
    }

    public function test_employee_cannot_print_until_the_campus_director_approves(): void
    {
        [$employee, $dean, $hr, $director] = $this->cast();
        $application = $this->submitForm($employee);

        $this->actingAs($employee)
            ->get(route('leave.print', $application))
            ->assertForbidden();

        foreach ([$dean, $hr] as $reviewer) {
            $this->actingAs($reviewer)->post(route('admin.leave.review.approve', $application));
        }

        // Two of three approvals is still not enough to print.
        $this->actingAs($employee)
            ->get(route('leave.print', $application))
            ->assertForbidden();

        $this->actingAs($director)->post(route('admin.leave.review.approve', $application));

        $this->actingAs($employee)
            ->get(route('leave.print', $application))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_returned_form_restarts_the_chain_after_reupload(): void
    {
        [$employee, $dean, $hr] = $this->cast();
        $application = $this->submitForm($employee);

        // HR returns it after the Dean has passed it along.
        $this->actingAs($dean)->post(route('admin.leave.review.approve', $application));

        $this->actingAs($hr)->post(route('admin.leave.review.return', $application), [
            'remarks' => 'The inclusive dates do not match the attached certificate.',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('hr_returned', $application->status);
        $this->assertTrue($application->isReturned());

        $this->actingAs($employee)->post(route('leave.resubmit', $application), [
            'leave_form' => UploadedFile::fake()->create('corrected.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('corrected.pdf', $application->file_original_name);
        $this->assertSame('pending', $application->dean_status);
        $this->assertSame('pending', $application->hr_status);
    }

    public function test_returning_a_form_requires_remarks(): void
    {
        [$employee, $dean] = $this->cast();
        $application = $this->submitForm($employee);

        $this->actingAs($dean)
            ->post(route('admin.leave.review.return', $application), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');

        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_hr_posts_the_approved_leave_to_the_ledger(): void
    {
        [$employee, $dean, $hr, $director] = $this->cast();
        $application = $this->submitForm($employee);

        foreach ([$dean, $hr, $director] as $reviewer) {
            $this->actingAs($reviewer)->post(route('admin.leave.review.approve', $application));
        }

        $this->actingAs($hr)->post(route('admin.leave.review.post-to-ledger', $application), [
            'period_from' => now()->addWeek()->format('Y-m-d'),
            'period_to' => now()->addWeek()->addDay()->format('Y-m-d'),
            'days' => 2,
            'vl_used' => 2,
            'vl_used_wop' => 0,
            'sl_used' => 0,
            'sl_used_wop' => 0,
            'service_used' => 0,
            'remarks' => 'VL — Family matter',
            // HR chooses which of the two cards this leave is written on.
            'ledger' => \App\Models\LeaveLedgerEntry::LEAVE,
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('completed', $application->status);
        $this->assertTrue($application->ledger_posted);
        $this->assertNotNull($application->leave_ledger_entry_id);

        // 10 days of VL less the 2 taken.
        $this->assertEquals(8.0, (float) $employee->fresh()->leaveBalance->vl_balance);

        $entry = $application->ledgerEntry;
        $this->assertEquals(2.0, (float) $entry->vl_used);
        $this->assertEquals(8.0, (float) $entry->vl_balance);
    }

    public function test_leave_without_pay_is_recorded_but_does_not_reduce_the_balance(): void
    {
        [$employee, $dean, $hr, $director] = $this->cast();
        $application = $this->submitForm($employee);

        foreach ([$dean, $hr, $director] as $reviewer) {
            $this->actingAs($reviewer)->post(route('admin.leave.review.approve', $application));
        }

        $this->actingAs($hr)->post(route('admin.leave.review.post-to-ledger', $application), [
            'period_from' => now()->addWeek()->format('Y-m-d'),
            'period_to' => now()->addWeek()->addDay()->format('Y-m-d'),
            'days' => 3,
            'vl_used' => 0,
            'vl_used_wop' => 3,
            'remarks' => 'VL without pay — no credits remaining',
            'ledger' => \App\Models\LeaveLedgerEntry::LEAVE,
        ])->assertRedirect();

        $entry = $application->fresh()->ledgerEntry;
        $this->assertEquals(3.0, (float) $entry->vl_used_wop);
        $this->assertEquals(10.0, (float) $entry->vl_balance);
        $this->assertEquals(10.0, (float) $employee->fresh()->leaveBalance->vl_balance);
    }

    public function test_ledger_cannot_be_posted_before_full_approval(): void
    {
        [$employee, $dean, $hr] = $this->cast();
        $application = $this->submitForm($employee);

        $this->actingAs($dean)->post(route('admin.leave.review.approve', $application));

        $this->actingAs($hr)->post(route('admin.leave.review.post-to-ledger', $application), [
            'period_from' => now()->format('Y-m-d'),
            'period_to' => now()->format('Y-m-d'),
            'days' => 1,
            'vl_used' => 1,
            'remarks' => 'Too early',
        ])->assertStatus(422);

        $this->assertFalse($application->fresh()->ledger_posted);
    }

    public function test_employee_downloads_the_template_hr_published(): void
    {
        Storage::fake('public');
        [$employee, , $hr] = $this->cast();

        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'CSC Form No. 6 (Revised 2020)',
            'template' => UploadedFile::fake()->create('csc-form-6.xlsx', 100),
        ])->assertRedirect();

        $template = LeaveFormTemplate::active();
        $this->assertNotNull($template);
        $this->assertSame('csc-form-6.xlsx', $template->original_filename);

        $this->actingAs($employee)
            ->get(route('leave.template.download'))
            ->assertOk()
            ->assertDownload('csc-form-6.xlsx');
    }

    public function test_publishing_a_template_deactivates_the_previous_one(): void
    {
        Storage::fake('public');
        [, , $hr] = $this->cast();

        foreach (['old.xlsx', 'new.xlsx'] as $name) {
            $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
                'label' => $name,
                // Distinct *content*, not just a distinct reported size:
                // fake()->create() writes a zero-byte file, so two of them
                // hash the same and are correctly treated as one version.
                'template' => UploadedFile::fake()->createWithContent($name, "workbook-{$name}"),
            ]);
        }

        // Publishing is additive: both versions exist, but only one is active.
        $this->assertSame(2, LeaveFormTemplate::count());
        $this->assertSame(1, LeaveFormTemplate::where('is_active', true)->count());
        $this->assertSame('new.xlsx', LeaveFormTemplate::active()->original_filename);
    }

    public function test_only_hr_can_manage_the_form_templates(): void
    {
        [, $dean] = $this->cast();

        $this->actingAs($dean)->get(route('admin.leave.templates.index'))->assertForbidden();
    }

    public function test_service_credits_are_kept_on_the_ledger_card(): void
    {
        [$employee, , $hr] = $this->cast();

        // Employment history as a separate record has been retired: what the
        // campus keeps is the service credit card, which is the second page
        // of the ledger and is written through the ledger itself.
        $this->actingAs($hr)->post(route('admin.leave.earned.store', $employee), [
            'period_from' => '2026-05-09',
            'period_to' => '2026-05-09',
            'service_earned' => 5,
            'remarks' => 'Service credits earned during the 2026 National and Local Elections',
            'ledger' => \App\Models\LeaveLedgerEntry::SERVICE,
        ])->assertRedirect();

        $this->assertDatabaseHas('leave_ledger_entries', [
            'user_id' => $employee->id,
            'ledger' => \App\Models\LeaveLedgerEntry::SERVICE,
        ]);

        // And it prints on the card the employee can open.
        $this->actingAs($employee)
            ->get(route('leave.ledger.pdf'))
            ->assertOk();
    }

    public function test_employee_cannot_reach_the_review_queue(): void
    {
        [$employee] = $this->cast();

        $this->actingAs($employee)
            ->get(route('admin.leave.review.index'))
            ->assertForbidden();
    }

    public function test_name_is_split_into_the_parts_the_ledger_card_prints(): void
    {
        $comma = $this->makeUser('employee', ['name' => 'Tresmanio, Olga S.']);
        $this->assertSame(
            ['family' => 'TRESMANIO', 'first' => 'OLGA', 'middle' => 'S.'],
            $comma->nameParts()
        );

        $plain = $this->makeUser('employee', ['name' => 'Juan Miguel Santos']);
        $this->assertSame(
            ['family' => 'SANTOS', 'first' => 'JUAN', 'middle' => 'M.'],
            $plain->nameParts()
        );
    }
}
