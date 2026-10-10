<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Notifications\LeaveStageChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Repeated, stale and conflicting decisions on one leave form.
 *
 * These run one request after another on SQLite. They prove that a stale or
 * repeated request is refused and writes nothing; they do NOT prove behaviour
 * under true simultaneity — that is tests/Integration (MariaDB, two processes).
 */
class LeaveDecisionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $dean;

    private User $hr;

    private User $hr2;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
        // A Monday, so the filed dates are working days.
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));

        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $this->employee = $this->person('employee', ['college_id' => $college->id, 'name' => 'Dela Cruz, Juan']);
        $this->dean = $this->person('dean', ['college_id' => $college->id, 'name' => 'Reyes, Maria']);
        $college->update(['dean_id' => $this->dean->id]);
        $this->hr = $this->person('admin', ['name' => 'Santos, Ana']);
        $this->hr2 = $this->person('admin', ['name' => 'Garcia, Ben']);
        $this->director = $this->person('campus_director', ['name' => 'Director, Carla']);

        LeaveBalance::create(['user_id' => $this->employee->id, 'vl_balance' => 10, 'sl_balance' => 10, 'service_balance' => 0]);
    }

    private function person(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'status' => 'active'], $attributes));
    }

    private function file(?User $who = null): LeaveApplication
    {
        $who ??= $this->employee;

        $this->actingAs($who)->post(route('leave.store'), [
            'leave_type' => 'VL', 'date_from' => '2026-10-19', 'date_to' => '2026-10-20',
            'leave_form' => UploadedFile::fake()->create('leave.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return LeaveApplication::where('user_id', $who->id)->latest('id')->firstOrFail();
    }

    private function approve(User $reviewer, LeaveApplication $application)
    {
        return $this->actingAs($reviewer)->post(route('admin.leave.review.approve', $application));
    }

    private function returnIt(User $reviewer, LeaveApplication $application, string $why = 'Wrong dates')
    {
        return $this->actingAs($reviewer)->post(route('admin.leave.review.return', $application), ['remarks' => $why]);
    }

    private function approvals(LeaveApplication $application, string $stage, ?string $action = null): int
    {
        return LeaveApproval::where('leave_application_id', $application->id)
            ->where('stage', $stage)
            ->when($action, fn ($q) => $q->where('action', $action))
            ->count();
    }

    // ------------------------------------------------------------------

    public function test_a_double_clicked_approval_is_recorded_once(): void
    {
        $application = $this->file();

        $this->approve($this->dean, $application)->assertRedirect(route('admin.leave.review.index'));
        $this->approve($this->dean, $application)
            ->assertRedirect(route('admin.leave.review.show', $application))
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'already approved at the Dean stage by Reyes, Maria'));

        $this->assertSame('dean_approved', $application->fresh()->status);
        $this->assertSame(1, $this->approvals($application, 'dean'));
    }

    public function test_a_second_hr_administrator_cannot_approve_the_same_stage(): void
    {
        $application = $this->file();
        $this->approve($this->dean, $application);

        $this->approve($this->hr, $application)->assertSessionHasNoErrors();
        // Opened the same form before the first approval landed.
        $this->approve($this->hr2, $application)
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'by Santos, Ana'));

        $application->refresh();
        $this->assertSame('hr_approved', $application->status);
        $this->assertSame($this->hr->id, $application->hr_id);
        $this->assertSame(1, $this->approvals($application, 'hr'));
    }

    public function test_approval_and_return_cannot_both_succeed(): void
    {
        $application = $this->file();
        $this->approve($this->dean, $application);

        $this->approve($this->hr, $application);
        $this->returnIt($this->hr2, $application)->assertSessionHas('warning');

        $application->refresh();
        $this->assertSame('hr_approved', $application->status);
        $this->assertNull($application->hr_remarks);
        $this->assertSame(0, $this->approvals($application, 'hr', 'returned'));
    }

    public function test_a_returned_form_cannot_be_approved_by_a_stale_request(): void
    {
        $application = $this->file();
        $this->approve($this->dean, $application);

        $this->returnIt($this->hr, $application, 'Attach the clearance')->assertSessionHasNoErrors();
        $this->approve($this->hr2, $application)
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'already returned at the HR Administrator stage'));

        $application->refresh();
        $this->assertSame('hr_returned', $application->status);
        $this->assertSame('Attach the clearance', $application->hr_remarks);
        $this->assertSame('Attach the clearance', $application->remarks);
        $this->assertSame(0, $this->approvals($application, 'hr', 'approved'));
    }

    public function test_a_repeated_return_is_recorded_and_notified_once(): void
    {
        $application = $this->file();
        Notification::fake();

        $this->returnIt($this->dean, $application);
        $this->returnIt($this->dean, $application)->assertSessionHas('warning');

        $this->assertSame(1, $this->approvals($application, 'dean', 'returned'));
        Notification::assertSentToTimes($this->employee, LeaveStageChanged::class, 1);
    }

    public function test_a_reviewer_at_another_stage_still_gets_403(): void
    {
        $application = $this->file();

        // Still with the Dean.
        $this->approve($this->hr, $application)->assertForbidden();
        $this->returnIt($this->director, $application)->assertForbidden();

        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertSame(0, LeaveApproval::count());
    }

    public function test_a_dean_of_another_college_gets_403_not_the_decision(): void
    {
        $application = $this->file();
        $this->approve($this->dean, $application);

        $other = College::firstOrCreate(['code' => 'CTE'], ['name' => 'College of Teacher Education']);
        $otherDean = $this->person('dean', ['college_id' => $other->id]);

        $this->approve($otherDean, $application)->assertForbidden();
    }

    public function test_a_failed_write_rolls_everything_back_and_notifies_nobody(): void
    {
        $application = $this->file();
        Notification::fake();

        LeaveApproval::creating(function () {
            throw new \RuntimeException('Simulated failure writing the approval history.');
        });

        $this->approve($this->dean, $application)->assertSessionHas('error');

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('pending', $application->dean_status);
        $this->assertNull($application->dean_id);
        Notification::assertNothingSent();
    }

    public function test_after_a_correction_the_chain_can_be_decided_again(): void
    {
        $application = $this->file();
        $this->returnIt($this->dean, $application);

        $this->actingAs($this->employee)->post(route('leave.resubmit', $application), [
            'leave_form' => UploadedFile::fake()->create('fixed.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->approve($this->dean, $application)->assertSessionHasNoErrors();

        $this->assertSame('dean_approved', $application->fresh()->status);
        $this->assertSame(1, $this->approvals($application, 'dean', 'returned'));
        $this->assertSame(1, $this->approvals($application, 'dean', 'approved'));
    }

    public function test_a_deans_own_leave_still_skips_the_dean_stage(): void
    {
        $application = $this->file($this->dean);

        $this->assertSame('dean_approved', $application->status);
        $this->approve($this->hr, $application)->assertSessionHasNoErrors();
        $this->approve($this->hr2, $application)->assertSessionHas('warning');
        $this->approve($this->director, $application)->assertSessionHasNoErrors();

        $this->assertSame('cd_approved', $application->fresh()->status);
        $this->assertSame(0, $this->approvals($application, 'dean'));
    }

    public function test_the_campus_directors_own_leave_goes_to_hr_alone(): void
    {
        LeaveBalance::create(['user_id' => $this->director->id, 'vl_balance' => 10, 'sl_balance' => 10]);
        $application = $this->file($this->director);

        $this->assertSame('dean_approved', $application->status);
        $this->approve($this->hr, $application)->assertSessionHasNoErrors();
        $this->approve($this->hr2, $application)->assertSessionHas('warning');

        $this->assertSame('cd_approved', $application->fresh()->status);
        $this->assertSame(1, LeaveApproval::where('leave_application_id', $application->id)->count());
    }

    // ------------------------------------------------------------------
    // Ledger posting
    // ------------------------------------------------------------------

    private function fullyApproved(): LeaveApplication
    {
        $application = $this->file();
        $this->approve($this->dean, $application);
        $this->approve($this->hr, $application);
        $this->approve($this->director, $application);

        return $application->fresh();
    }

    private function postToLedger(LeaveApplication $application, array $overrides = [])
    {
        return $this->actingAs($this->hr)->post(route('admin.leave.review.post-to-ledger', $application), $overrides + [
            'ledger' => 'leave', 'period_from' => '2026-10-19', 'period_to' => '2026-10-20',
            'days' => 2, 'vl_used' => 2, 'remarks' => 'VL',
        ]);
    }

    public function test_repeated_posting_deducts_once(): void
    {
        $application = $this->fullyApproved();

        $this->postToLedger($application)->assertSessionHasNoErrors();
        $this->postToLedger($application)->assertStatus(422);

        $this->assertSame(1, LeaveLedgerEntry::where('leave_application_id', $application->id)->count());
        $this->assertEquals(8, $this->employee->leaveBalance->fresh()->vl_balance);
        $this->assertSame('completed', $application->fresh()->status);
    }

    public function test_posting_before_final_approval_is_refused(): void
    {
        $application = $this->file();
        $this->approve($this->dean, $application);
        $this->approve($this->hr, $application);

        $this->postToLedger($application)->assertStatus(422);

        $this->assertSame(0, LeaveLedgerEntry::count());
        $this->assertEquals(10, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    public function test_a_posting_that_would_go_negative_rolls_back_completely(): void
    {
        $application = $this->fullyApproved();

        $this->postToLedger($application, ['vl_used' => 50, 'adjustment_reason' => 'Test: over-charge', 'confirm_adjustment' => 1])->assertSessionHas('error');

        $application->refresh();
        $this->assertSame('cd_approved', $application->status);
        $this->assertFalse((bool) $application->ledger_posted);
        $this->assertSame(0, LeaveLedgerEntry::count());
        $this->assertEquals(10, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    public function test_the_balance_matches_the_last_line_of_the_card(): void
    {
        $application = $this->fullyApproved();
        $this->postToLedger($application);

        $last = LeaveLedgerEntry::where('user_id', $this->employee->id)->latest('id')->first();
        $balance = $this->employee->leaveBalance->fresh();

        $this->assertEquals($balance->vl_balance, $last->vl_balance);
        $this->assertEquals($balance->sl_balance, $last->sl_balance);
    }

    public function test_removing_a_posted_line_puts_the_form_back_awaiting_posting(): void
    {
        // The opening balance on the card itself, as HR keeps it: removing a
        // line replays the card, so a balance set outside it would not survive.
        $this->employee->leaveBalance->update(['vl_balance' => 0, 'sl_balance' => 0]);
        $this->actingAs($this->hr);
        app(\App\Services\LeaveLedgerService::class)->postEntry(
            employee: $this->employee, periodFrom: '2026-01-01', periodTo: '2026-01-01',
            type: 'adjustment', remarks: 'Balance forwarded', vlEarned: 10, slEarned: 10,
        );

        $application = $this->fullyApproved();
        $this->postToLedger($application);
        $entry = LeaveLedgerEntry::where('leave_application_id', $application->id)->sole();

        $this->actingAs($this->hr)->delete(route('admin.leave.ledger.entry.destroy', $entry))->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('cd_approved', $application->status);
        $this->assertFalse((bool) $application->ledger_posted);
        $this->assertNull($application->leave_ledger_entry_id);
        $this->assertEquals(10, $this->employee->leaveBalance->fresh()->vl_balance);

        // And it can be posted again, correctly, once.
        $this->postToLedger($application, ['vl_used' => 1, 'days' => 1, 'period_to' => '2026-10-19', 'adjustment_reason' => 'Returned to work on the second day', 'confirm_adjustment' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(9, $this->employee->leaveBalance->fresh()->vl_balance);
        $this->assertSame('completed', $application->fresh()->status);
    }

    public function test_an_explicit_duplicate_accrual_is_still_allowed(): void
    {
        // HR's confirmed duplicate credit is a different thing from posting
        // the same leave form twice, and must keep working.
        $route = route('admin.leave.earned.store', $this->employee);
        $payload = ['period_from' => '2026-09-01', 'period_to' => '2026-09-30', 'vl_earned' => 1.25, 'sl_earned' => 1.25, 'ledger' => 'leave'];

        $this->actingAs($this->hr)->post($route, $payload)->assertSessionHas('success');
        // Unconfirmed, the second credit for the same month is refused…
        $this->post($route, $payload)->assertSessionHas('error');
        // …and confirmed, it goes through.
        $this->post($route, $payload + ['allow_duplicate' => 1])->assertSessionHas('success');

        $this->assertSame(2, LeaveLedgerEntry::where('user_id', $this->employee->id)->where('type', 'earned')->count());
        $this->assertEquals(12.5, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    // ------------------------------------------------------------------
    // Official records
    // ------------------------------------------------------------------

    public function test_an_employee_with_official_records_cannot_be_deleted(): void
    {
        $application = $this->fullyApproved();

        try {
            $this->employee->delete();
            $this->fail('Deleting an employee with leave records must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Deactivate the account instead', $e->getMessage());
        }

        $this->assertNotNull(User::find($this->employee->id));
        $this->assertNotNull(LeaveApplication::find($application->id));
        $this->assertSame(3, LeaveApproval::where('leave_application_id', $application->id)->count());
    }

    public function test_a_reviewer_who_signed_forms_cannot_be_deleted(): void
    {
        $this->fullyApproved();

        $this->expectException(\RuntimeException::class);
        $this->dean->delete();
    }

    public function test_an_account_with_no_records_can_still_be_removed(): void
    {
        $spare = $this->person('employee');

        $spare->delete();

        $this->assertNull(User::find($spare->id));
    }

    public function test_deactivation_keeps_every_record(): void
    {
        $application = $this->fullyApproved();
        $this->postToLedger($application);

        $this->actingAs($this->hr)->patch(route('admin.employees.status.update', $this->employee), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $this->employee->fresh()->status);
        $this->assertSame(1, LeaveApplication::where('user_id', $this->employee->id)->count());
        $this->assertSame(1, LeaveLedgerEntry::where('user_id', $this->employee->id)->count());
        $this->assertSame(3, LeaveApproval::where('leave_application_id', $application->id)->count());
    }
}
