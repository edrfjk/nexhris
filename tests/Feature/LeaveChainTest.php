<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Notifications\LeaveStageChanged;
use App\Services\LeaveChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The approval chain is derived from who is applying, so nobody signs their
 * own leave. These cover each applicant role and the notification routing.
 */
class LeaveChainTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' ' . fake()->unique()->numberBetween(1, 999),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'college_id' => College::where('code', 'CAS')->value('id'),
        ], $overrides));
    }

    private function chain(): LeaveChain
    {
        return app(LeaveChain::class);
    }

    // ------------------------------------------------------------------
    // Chain shape per applicant
    // ------------------------------------------------------------------

    public function test_a_regular_employee_passes_all_three_stages(): void
    {
        $employee = $this->user('employee');

        $this->assertSame(['dean', 'hr', 'campus_director'], $this->chain()->stagesFor($employee));
        $this->assertSame([], $this->chain()->skippedFor($employee));
        $this->assertSame('submitted', $this->chain()->initialStatus($employee));
        $this->assertSame('campus_director', $this->chain()->finalStage($employee));
    }

    public function test_a_dean_skips_their_own_stage(): void
    {
        $dean = $this->user('dean');

        $this->assertSame(['hr', 'campus_director'], $this->chain()->stagesFor($dean));
        $this->assertSame(['dean'], $this->chain()->skippedFor($dean));

        // Starts at HR, not at the Dean stage.
        $this->assertSame('dean_approved', $this->chain()->initialStatus($dean));
        $this->assertSame('campus_director', $this->chain()->finalStage($dean));
    }

    public function test_the_campus_director_ends_at_hr_as_final_approver(): void
    {
        $director = $this->user('campus_director');

        // HR alone, per the client. A Dean reports to the Campus Director,
        // so no Dean signs their form, and HR is final as nobody outranks
        // the Campus Director.
        $this->assertSame(['hr'], $this->chain()->stagesFor($director));
        $this->assertSame(['dean', 'campus_director'], $this->chain()->skippedFor($director));
        $this->assertSame('hr', $this->chain()->finalStage($director));
    }

    public function test_hr_skips_their_own_stage(): void
    {
        $hr = $this->user('admin');

        $this->assertSame(['dean', 'campus_director'], $this->chain()->stagesFor($hr));
        $this->assertSame(['hr'], $this->chain()->skippedFor($hr));
    }

    // ------------------------------------------------------------------
    // Transitions end to end
    // ------------------------------------------------------------------

    private function fileLeave(User $applicant): LeaveApplication
    {
        Storage::fake('public');

        LeaveBalance::firstOrCreate(['user_id' => $applicant->id], [
            'vl_balance' => 20, 'sl_balance' => 20, 'service_balance' => 0,
        ]);

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => now()->addWeek()->format('Y-m-d'),
            'date_to' => now()->addWeek()->addDay()->format('Y-m-d'),
            'reason' => 'Personal',
            'leave_form' => UploadedFile::fake()->create('form.xlsx', 40),
        ])->assertRedirect();

        return LeaveApplication::where('user_id', $applicant->id)->latest()->firstOrFail();
    }

    public function test_a_deans_own_leave_starts_at_hr_and_ends_at_the_campus_director(): void
    {
        $dean = $this->user('dean');
        $hr = $this->user('admin');
        $director = $this->user('campus_director');

        $application = $this->fileLeave($dean);

        // The Dean stage was skipped entirely.
        $this->assertSame('dean_approved', $application->status);
        $this->assertSame('hr', $application->currentStage());

        $this->actingAs($hr)->post(route('admin.leave.review.approve', $application))->assertRedirect();
        $this->assertSame('campus_director', $application->fresh()->currentStage());

        $this->actingAs($director)->post(route('admin.leave.review.approve', $application))->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveChain::APPROVED, $application->status);
        $this->assertTrue($application->isFullyApproved());
    }

    public function test_the_campus_directors_own_leave_goes_to_hr_alone(): void
    {
        $director = $this->user('campus_director');
        $hr = $this->user('admin');

        $application = $this->fileLeave($director);

        // It lands on HR's desk immediately — no Dean stage to clear first.
        $this->assertSame('hr', $application->currentStage());

        // And HR's approval is the last word.
        $this->actingAs($hr)->post(route('admin.leave.review.approve', $application))->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveChain::APPROVED, $application->status);
        $this->assertTrue($application->isFullyApproved());
    }

    public function test_no_dean_is_asked_to_sign_the_campus_directors_leave(): void
    {
        $director = $this->user('campus_director');
        $dean = $this->user('dean');

        $application = $this->fileLeave($director);

        // A Dean reports to the Campus Director; signing their leave would
        // invert the reporting line.
        $this->actingAs($dean)
            ->get(route('admin.leave.review.index'))
            ->assertOk()
            ->assertDontSee($director->name);

        $this->actingAs($dean)
            ->post(route('admin.leave.review.approve', $application))
            ->assertForbidden();
    }

    public function test_the_campus_director_cannot_approve_their_own_leave(): void
    {
        $director = $this->user('campus_director');
        $dean = $this->user('dean');

        $application = $this->fileLeave($director);
        $this->actingAs($dean)->post(route('admin.leave.review.approve', $application));

        // Sitting at HR; the Director must not be able to push it through.
        $this->actingAs($director)
            ->post(route('admin.leave.review.approve', $application))
            ->assertForbidden();
    }

    public function test_a_dean_cannot_approve_their_own_leave(): void
    {
        $dean = $this->user('dean');
        $application = $this->fileLeave($dean);

        $this->actingAs($dean)
            ->post(route('admin.leave.review.approve', $application))
            ->assertForbidden();
    }

    public function test_a_reviewers_own_form_never_appears_in_their_queue(): void
    {
        $hr = $this->user('admin');
        $application = $this->fileLeave($hr);

        // HR's own form skips the HR stage, so it sits with the Dean.
        $this->assertSame('dean', $application->currentStage());

        // Assert on the queue itself: the signed-in user's own name always
        // appears in the application bar, so scanning the whole page would
        // pass for the wrong reason.
        $queue = app(\App\Services\LeaveWorkflowService::class)->queueFor($hr)->pluck('id');

        $this->assertNotContains($application->id, $queue->all());

        $this->actingAs($hr)
            ->get(route('admin.leave.review.index'))
            ->assertOk()
            ->assertDontSee(route('admin.leave.review.show', $application));
    }

    // ------------------------------------------------------------------
    // Stepper
    // ------------------------------------------------------------------

    public function test_a_skipped_stage_is_reported_as_such(): void
    {
        $dean = $this->user('dean');
        $application = $this->fileLeave($dean);

        $timeline = collect($application->timeline())->keyBy('stage');

        $this->assertSame('skipped', $timeline['dean']['state']);
        $this->assertSame('current', $timeline['hr']['state']);
        $this->assertSame('pending', $timeline['campus_director']['state']);
    }

    public function test_the_employee_sees_the_skipped_stage_marked_na(): void
    {
        $dean = $this->user('dean');
        $this->fileLeave($dean);

        $this->actingAs($dean)
            ->get(route('leave.index'))
            ->assertOk()
            ->assertSee('N/A — own stage');
    }

    // ------------------------------------------------------------------
    // Balance check
    // ------------------------------------------------------------------

    public function test_submitting_more_days_than_available_warns_the_employee(): void
    {
        Storage::fake('public');
        $employee = $this->user('employee');

        LeaveBalance::create([
            'user_id' => $employee->id, 'vl_balance' => 1, 'sl_balance' => 0, 'service_balance' => 0,
        ]);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => now()->addWeek()->format('Y-m-d'),
            'date_to' => now()->addWeek()->addDays(6)->format('Y-m-d'),
            'leave_form' => UploadedFile::fake()->create('form.xlsx', 40),
        ])->assertSessionHas('warning');

        // Advisory only — the form is still filed.
        $this->assertDatabaseCount('leave_applications', 1);
    }

    public function test_a_sufficient_balance_produces_no_warning(): void
    {
        Storage::fake('public');
        $employee = $this->user('employee');

        LeaveBalance::create([
            'user_id' => $employee->id, 'vl_balance' => 30, 'sl_balance' => 30, 'service_balance' => 0,
        ]);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => now()->addWeek()->format('Y-m-d'),
            'date_to' => now()->addWeek()->addDay()->format('Y-m-d'),
            'leave_form' => UploadedFile::fake()->create('form.xlsx', 40),
        ])->assertSessionMissing('warning');
    }

    // ------------------------------------------------------------------
    // Notification routing
    // ------------------------------------------------------------------

    public function test_submission_notifies_the_dean_of_the_employees_own_college(): void
    {
        Notification::fake();

        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $ourDean = $this->user('dean', ['college_id' => $cas->id]);
        $otherDean = $this->user('dean', ['college_id' => $cte->id]);
        $cas->update(['dean_id' => $ourDean->id]);
        $cte->update(['dean_id' => $otherDean->id]);

        $employee = $this->user('employee', ['college_id' => $cas->id]);

        $this->fileLeave($employee);

        Notification::assertSentTo($ourDean, LeaveStageChanged::class);
        Notification::assertNotSentTo($otherDean, LeaveStageChanged::class);
    }

    public function test_full_approval_notifies_the_employee_and_hr(): void
    {
        $employee = $this->user('employee');
        $dean = $this->user('dean');
        $hr = $this->user('admin');
        $director = $this->user('campus_director');

        $application = $this->fileLeave($employee);

        Notification::fake();

        $this->actingAs($dean)->post(route('admin.leave.review.approve', $application));
        $this->actingAs($hr)->post(route('admin.leave.review.approve', $application));
        $this->actingAs($director)->post(route('admin.leave.review.approve', $application));

        // The employee is told they can print; HR is told to post the ledger.
        Notification::assertSentTo($employee, LeaveStageChanged::class);
        Notification::assertSentTo($hr, LeaveStageChanged::class);
    }

    public function test_a_return_notifies_the_employee(): void
    {
        $employee = $this->user('employee');
        $dean = $this->user('dean');

        $application = $this->fileLeave($employee);

        Notification::fake();

        $this->actingAs($dean)->post(route('admin.leave.review.return', $application), [
            'remarks' => 'Dates do not match the medical certificate.',
        ])->assertRedirect();

        Notification::assertSentTo($employee, LeaveStageChanged::class);
    }

    public function test_a_form_with_no_reachable_dean_is_recorded(): void
    {
        Notification::fake();

        // An employee whose college has no Dean at all.
        $orphan = College::create(['code' => 'ORP', 'name' => 'Unstaffed Office']);
        $employee = $this->user('employee', ['college_id' => $orphan->id]);

        $this->fileLeave($employee);

        $this->assertDatabaseHas('activity_logs', ['action' => 'leave.no_reviewer']);
    }

    public function test_in_app_notifications_are_stored_for_the_bell(): void
    {
        $employee = $this->user('employee');
        $dean = $this->user('dean');
        College::where('code', 'CAS')->update(['dean_id' => $dean->id]);

        $this->fileLeave($employee);

        $this->assertGreaterThan(0, $dean->unreadNotifications()->count());

        $this->actingAs($dean)->get(route('notifications.index'))->assertOk();
    }
}
