<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\LeavePostingAdjustment;
use App\Models\User;
use App\Services\LeaveForm\LeaveFormPrintout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * LEDGER-03: the approved leave form keeps its figures; anything HR posts
 * differently is recorded as an append-only adjustment with a reason.
 */
class LeavePostingAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));

        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $this->employee = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $college->id, 'name' => 'Dela Cruz, Juan']);
        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Santos, Ana']);
        LeaveBalance::create(['user_id' => $this->employee->id, 'vl_balance' => 10, 'sl_balance' => 10, 'service_balance' => 3]);
    }

    /** Fully approved VL, Oct 19–21 (3 days), with credits certified at 10 VL. */
    private function approved(array $attributes = []): LeaveApplication
    {
        return LeaveApplication::create($attributes + [
            'user_id' => $this->employee->id, 'leave_type' => 'VL',
            'date_from' => '2026-10-19', 'date_to' => '2026-10-21', 'days' => 3,
            'status' => 'cd_approved', 'dean_status' => 'approved', 'hr_status' => 'approved', 'director_status' => 'approved',
            'credit_certification' => ['as_of' => '2026-10-06', 'vl' => 10, 'sl' => 10, 'service' => 3],
        ]);
    }

    private function postIt(LeaveApplication $application, array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->hr)->post(route('admin.leave.review.post-to-ledger', $application), $overrides + [
            'ledger' => 'leave', 'period_from' => '2026-10-19', 'period_to' => '2026-10-21',
            'days' => 3, 'vl_used' => 3, 'remarks' => 'VL',
        ]);
    }

    private function adjusted(): array
    {
        return ['period_to' => '2026-10-20', 'days' => 2, 'vl_used' => 2];
    }

    // ------------------------------------------------------------------

    public function test_a_posting_that_matches_needs_no_reason_and_records_no_adjustment(): void
    {
        $application = $this->approved();

        $this->postIt($application)->assertSessionHasNoErrors()->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('completed', $application->status);
        $this->assertNotNull($application->posting_checked_at);
        $this->assertSame(0, LeavePostingAdjustment::count());
        $this->assertEquals(7, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    public function test_different_figures_without_a_reason_are_refused_and_nothing_is_written(): void
    {
        $application = $this->approved();

        $this->postIt($application, $this->adjusted())
            ->assertSessionHasErrors(['adjustment_reason' => 'These figures differ from the approved form (last day: approved 2026-10-21, posted 2026-10-20; days: approved 3, posted 2; VL with pay: approved 3, posted 2). Give the reason for the adjustment and tick the confirmation to post them.']);

        $application->refresh();
        $this->assertSame('cd_approved', $application->status);
        $this->assertFalse($application->ledger_posted);
        $this->assertSame(0, LeaveLedgerEntry::count());
        $this->assertSame(0, LeavePostingAdjustment::count());
        $this->assertEquals(10, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    public function test_a_reason_without_the_confirmation_is_refused(): void
    {
        $application = $this->approved();

        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Came back early'])
            ->assertSessionHasErrors('adjustment_reason');

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_a_confirmed_adjustment_posts_and_keeps_the_approved_figures(): void
    {
        $application = $this->approved();

        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Returned to work on Oct 21', 'confirm_adjustment' => 1])
            ->assertSessionHasNoErrors();

        // The approved form is untouched…
        $application->refresh();
        $this->assertSame('2026-10-19', $application->date_from->toDateString());
        $this->assertSame('2026-10-21', $application->date_to->toDateString());
        $this->assertEquals(3, $application->days);
        $this->assertSame('completed', $application->status);

        // …the ledger carries what was posted…
        $entry = $application->ledgerEntry;
        $this->assertSame('2026-10-20', $entry->period_to->toDateString());
        $this->assertEquals(2, $entry->vl_used);
        $this->assertEquals(8, $this->employee->leaveBalance->fresh()->vl_balance);

        // …and the adjustment says what, why and who.
        $adjustment = LeavePostingAdjustment::sole();
        $this->assertSame($application->id, $adjustment->leave_application_id);
        $this->assertSame($entry->id, $adjustment->leave_ledger_entry_id);
        $this->assertEquals(3, $adjustment->original_days);
        $this->assertEquals(2, $adjustment->posted_days);
        $this->assertSame('2026-10-21', $adjustment->original_date_to->toDateString());
        $this->assertSame('2026-10-20', $adjustment->posted_date_to->toDateString());
        $this->assertEquals(3, $adjustment->original_charges['vl_used']);
        $this->assertEquals(2, $adjustment->posted_charges['vl_used']);
        $this->assertSame(['date_to', 'days', 'vl_used'], array_column($adjustment->differences, 'field'));
        $this->assertSame('Returned to work on Oct 21', $adjustment->reason);
        $this->assertSame($this->hr->id, $adjustment->adjusted_by);
    }

    public function test_an_adjustment_is_written_to_the_activity_log(): void
    {
        $application = $this->approved();

        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Returned early', 'confirm_adjustment' => 1]);

        $log = ActivityLog::where('action', 'leave.posting_adjusted')->sole();
        $this->assertSame($this->hr->id, $log->user_id);
        $this->assertStringContainsString('days: approved 3, posted 2', $log->description);
        $this->assertSame('Returned early', $log->properties['reason']);
    }

    public function test_moving_days_to_without_pay_is_an_adjustment(): void
    {
        $application = $this->approved();
        $split = ['vl_used' => 1, 'vl_used_wop' => 2];

        $this->postIt($application, $split)->assertSessionHasErrors('adjustment_reason');

        $this->postIt($application, $split + ['adjustment_reason' => 'Two days without pay per HR', 'confirm_adjustment' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(['vl_used', 'vl_used_wop'], array_column(LeavePostingAdjustment::sole()->differences, 'field'));
        $this->assertEquals(9, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    public function test_certified_without_pay_days_are_the_expected_split(): void
    {
        // Only 1 VL certified: the approved form already reads 1 with pay,
        // 2 without, so posting exactly that is not an adjustment.
        $this->employee->leaveBalance->update(['vl_balance' => 1]);
        $application = $this->approved(['credit_certification' => ['as_of' => '2026-10-06', 'vl' => 1, 'sl' => 10, 'service' => 3]]);

        $this->postIt($application, ['vl_used' => 1, 'vl_used_wop' => 2])->assertSessionHasNoErrors();

        $this->assertSame(0, LeavePostingAdjustment::count());
    }

    public function test_a_failure_writing_the_adjustment_rolls_the_posting_back(): void
    {
        $application = $this->approved();
        LeavePostingAdjustment::creating(function () {
            throw new \RuntimeException('Simulated failure writing the adjustment.');
        });

        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'x', 'confirm_adjustment' => 1])
            ->assertSessionHas('error');

        $application->refresh();
        $this->assertSame('cd_approved', $application->status);
        $this->assertFalse($application->ledger_posted);
        $this->assertNull($application->posting_checked_at);
        $this->assertSame(0, LeaveLedgerEntry::count());
        $this->assertEquals(10, $this->employee->leaveBalance->fresh()->vl_balance);
    }

    public function test_an_adjustment_cannot_be_changed_or_deleted(): void
    {
        $application = $this->approved();
        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Returned early', 'confirm_adjustment' => 1]);
        $adjustment = LeavePostingAdjustment::sole();

        foreach ([fn () => $adjustment->update(['reason' => 'Rewritten']), fn () => $adjustment->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('An adjustment must be append-only.');
            } catch (\LogicException) {
                // expected
            }
        }

        $this->assertSame('Returned early', $adjustment->fresh()->reason);
    }

    public function test_removing_the_posted_line_keeps_the_adjustment_record(): void
    {
        // Opening balance on the card, so the replay after removal keeps it.
        $this->employee->leaveBalance->update(['vl_balance' => 0, 'sl_balance' => 0, 'service_balance' => 0]);
        $this->actingAs($this->hr);
        app(\App\Services\LeaveLedgerService::class)->postEntry(
            employee: $this->employee, periodFrom: '2026-01-01', periodTo: '2026-01-01',
            type: 'adjustment', remarks: 'Balance forwarded', vlEarned: 10, slEarned: 10,
        );
        $application = $this->approved();
        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Returned early', 'confirm_adjustment' => 1]);

        $this->delete(route('admin.leave.ledger.entry.destroy', $application->fresh()->ledgerEntry))->assertSessionHasNoErrors();

        $adjustment = LeavePostingAdjustment::sole();
        $this->assertNull($adjustment->leave_ledger_entry_id);
        $this->assertSame('Returned early', $adjustment->reason);
        $application->refresh();
        $this->assertSame('cd_approved', $application->status);
        $this->assertNull($application->posting_checked_at);
    }

    public function test_correcting_the_posted_line_keeps_the_adjustment_and_the_approved_form(): void
    {
        $application = $this->approved();
        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Returned early', 'confirm_adjustment' => 1]);
        $entry = $application->fresh()->ledgerEntry;
        $before = LeavePostingAdjustment::sole()->getAttributes();

        // HR reverses the deduction on the line itself (sets it to zero).
        $this->put(route('admin.leave.ledger.entry.update', $entry), [
            'period_from' => $entry->period_from->format('Y-m-d'),
            'period_to' => $entry->period_to->format('Y-m-d'),
            'remarks' => 'Deduction reversed',
            'vl_earned' => 0, 'vl_used' => 0, 'vl_used_wop' => 0,
            'sl_earned' => 0, 'sl_used' => 0, 'sl_used_wop' => 0,
            'service_earned' => 0, 'service_used' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, $entry->fresh()->vl_used);
        // The audit record is exactly as written, still linked to its line…
        $this->assertSame($before, LeavePostingAdjustment::sole()->getAttributes());
        // …and the approved form is unchanged.
        $application->refresh();
        $this->assertEquals(3, $application->days);
        $this->assertSame('2026-10-21', $application->date_to->toDateString());
        $this->assertEquals(3, LeaveFormPrintout::forApplication($application)['decision']['with_pay']);
    }

    public function test_only_hr_can_post_an_adjustment(): void
    {
        $application = $this->approved();
        $payload = $this->adjusted() + ['adjustment_reason' => 'x', 'confirm_adjustment' => 1];

        foreach (['dean', 'campus_director', 'employee'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active', 'college_id' => $this->employee->college_id]);
            $this->postIt($application, $payload, $user)->assertForbidden();
        }

        $this->assertSame(0, LeavePostingAdjustment::count());
        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_a_service_leave_on_the_service_card_compares_as_one_charge(): void
    {
        $application = $this->approved(['leave_type' => 'SERVICE']);

        // Any with-pay column on the service card is a service-credit charge.
        $this->postIt($application, ['ledger' => 'service', 'vl_used' => 3])->assertSessionHasNoErrors();

        $this->assertSame(0, LeavePostingAdjustment::count());
        $this->assertEquals(0, $this->employee->leaveBalance->fresh()->service_balance);
    }

    public function test_moving_vacation_leave_onto_the_service_card_is_an_adjustment(): void
    {
        $application = $this->approved();

        $this->postIt($application, ['ledger' => 'service', 'vl_used' => 3])->assertSessionHasErrors('adjustment_reason');
    }

    // ------------------------------------------------------------------
    // CS Form No. 6 and history
    // ------------------------------------------------------------------

    public function test_cs_form_6_keeps_the_approved_figures_after_an_adjusted_posting(): void
    {
        $application = $this->approved();
        $this->postIt($application, ['period_to' => '2026-10-20', 'days' => 2, 'vl_used' => 1, 'vl_used_wop' => 1,
            'adjustment_reason' => 'One day without pay; returned early', 'confirm_adjustment' => 1])->assertSessionHasNoErrors();

        $form = LeaveFormPrintout::forApplication($application->fresh());

        $this->assertSame('2026-10-19', $form['date_from']);
        $this->assertSame('2026-10-21', $form['date_to']);
        $this->assertEquals(3, $form['days']);
        // 7.C: approved for 3 days with pay (certified 10 VL), not the posted 1 + 1.
        $this->assertTrue($form['decision']['approved']);
        $this->assertEquals(3, $form['decision']['with_pay']);
        $this->assertEquals(0, $form['decision']['without_pay']);
    }

    public function test_a_form_posted_before_tracking_prints_exactly_as_before(): void
    {
        // Posted the old way: figures overwritten, no posting_checked_at.
        $application = $this->approved(['date_to' => '2026-10-20', 'days' => 2, 'status' => 'completed', 'ledger_posted' => true]);
        $this->actingAs($this->hr);
        $entry = app(\App\Services\LeaveLedgerService::class)->postEntry(
            employee: $this->employee, periodFrom: '2026-10-19', periodTo: '2026-10-20', type: 'leave_deduction',
            remarks: 'VL', vlUsed: 1, vlUsedWop: 1, leaveApplicationId: $application->id,
        );
        $application->update(['leave_ledger_entry_id' => $entry->id]);

        $form = LeaveFormPrintout::forApplication($application->fresh());

        // As it always printed: from the ledger line.
        $this->assertEquals(1, $form['decision']['with_pay']);
        $this->assertEquals(1, $form['decision']['without_pay']);
        $this->assertEquals(2, $form['days']);

        $this->get(route('admin.leave.review.show', $application))
            ->assertOk()->assertSee('Posted before adjustment tracking began');
        $this->assertSame(0, LeavePostingAdjustment::count());
    }

    public function test_hr_sees_the_adjustment_on_the_form_and_the_ledger_card(): void
    {
        $application = $this->approved();
        $this->postIt($application, $this->adjusted() + ['adjustment_reason' => 'Returned to work on Oct 21', 'confirm_adjustment' => 1]);

        $this->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->assertSee('Posted with adjustments')
            ->assertSee('Returned to work on Oct 21')
            ->assertSee('Santos, Ana');

        $this->get(route('admin.leave.ledger', $this->employee))
            ->assertOk()
            ->assertSee('Posting adjustments')
            ->assertSee('Returned to work on Oct 21');
    }

    public function test_the_posting_form_shows_the_approved_figures_and_the_reason_field(): void
    {
        $application = $this->approved();

        $this->actingAs($this->hr)->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->assertSee('Approved: 3 day(s)', false)
            ->assertSee('name="adjustment_reason"', false)
            ->assertSee('name="confirm_adjustment"', false);
    }
}
