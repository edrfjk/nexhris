<?php

namespace Tests\Feature;

use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Services\LeaveLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Faults found in the final QA pass, each pinned so it cannot come back.
 */
class QaRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'employee', array $overrides = []): User
    {
        return User::create(array_merge([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Dela Cruz, Juan M.',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'correct-horse-battery',
            'role' => $role,
            'status' => 'active',
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Sign-in lockout
    // ------------------------------------------------------------------

    public function test_a_locked_account_is_told_the_wait_in_whole_minutes(): void
    {
        $user = $this->user();
        $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(10)->subSeconds(20)])->save();

        // Read back from the database, where the value is a string until it
        // is cast. Uncast, isFuture() on it was a 500.
        $response = $this->post('/login', [
            'email' => $user->email, 'password' => 'correct-horse-battery', 'terms' => '1',
        ]);

        $response->assertSessionHasErrors('email');
        $message = session('errors')->first('email');

        $this->assertMatchesRegularExpression('/Try again in \d+ minute\(s\)/', $message);
        $this->assertStringContainsString('in 10 minute(s)', $message);
        $this->assertGuest();
    }

    public function test_an_expired_lock_starts_a_fresh_count(): void
    {
        $user = $this->user();
        $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->subMinute()])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong', 'terms' => '1']);

        // One typo after the lock ran out is one failure, not a new lock.
        $user->refresh();
        $this->assertNull($user->locked_until);
        $this->assertSame(1, (int) $user->failed_login_attempts);
    }

    // ------------------------------------------------------------------
    // Profile name
    // ------------------------------------------------------------------

    public function test_the_profile_edits_the_name_parts_the_ledger_card_prints(): void
    {
        $user = $this->user('employee', ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'name' => 'Juan Dela Cruz']);

        $this->actingAs($user)->put(route('profile.update'), [
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Maria Santos Reyes', $user->name);
        $this->assertSame(['family' => 'REYES', 'first' => 'MARIA', 'middle' => 'S.'], $user->nameParts());
    }

    public function test_a_plain_name_change_does_not_leave_the_old_parts_on_the_card(): void
    {
        $user = $this->user('employee', ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'name' => 'Juan Dela Cruz']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Maria Reyes',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('REYES', $user->fresh()->nameParts()['family']);
    }

    // ------------------------------------------------------------------
    // Leave calendar
    // ------------------------------------------------------------------

    public function test_an_unreadable_month_shows_the_current_one_instead_of_failing(): void
    {
        $hr = $this->user('admin');

        foreach (['abc', '2026-13', '2026-1', ''] as $month) {
            $this->actingAs($hr)->get(route('admin.leave.calendar', ['month' => $month]))
                ->assertOk()
                ->assertSee(now()->format('Y-m'), false);
        }

        $this->actingAs($hr)->get(route('admin.leave.calendar.export', ['month' => 'abc']))->assertOk();
    }

    // ------------------------------------------------------------------
    // Ledger running balances
    // ------------------------------------------------------------------

    public function test_a_backdated_posting_keeps_every_running_balance_right(): void
    {
        $hr = $this->user('admin');
        $employee = $this->user();
        $service = app(LeaveLedgerService::class);

        $this->actingAs($hr);

        $service->postEntry($employee, '2026-03-01', '2026-03-31', 'earned', vlEarned: 1.25);
        $april = $service->postEntry($employee, '2026-04-01', '2026-04-30', 'earned', vlEarned: 1.25);

        // Leave taken in March, approved and posted after April's credit.
        $march = $service->postEntry($employee, '2026-03-20', '2026-03-20', 'leave_deduction', vlUsed: 1);

        $this->assertEquals(0.25, (float) $march->vl_balance);
        $this->assertEquals(1.50, (float) $april->fresh()->vl_balance);
        $this->assertEquals(1.50, (float) $employee->leaveBalance()->first()->vl_balance);

        $rows = LeaveLedgerEntry::where('user_id', $employee->id)
            ->orderBy('period_from')->orderBy('id')->pluck('vl_balance')->map(fn ($v) => (float) $v)->all();
        $this->assertSame([1.25, 0.25, 1.5], $rows);
    }

    // ------------------------------------------------------------------
    // Leave form uploads
    // ------------------------------------------------------------------

    private function file(User $employee, string $name, string $mime, string $from = '2026-11-02', string $to = '2026-11-03')
    {
        return $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => $from,
            'date_to' => $to,
            'leave_form' => UploadedFile::fake()->create($name, 20, $mime),
        ]);
    }

    public function test_a_pdf_leave_form_is_shown_and_downloaded_as_a_pdf(): void
    {
        Storage::fake('local');
        Notification::fake();
        $employee = $this->user();

        $this->file($employee, 'form.pdf', 'application/pdf')->assertSessionHasNoErrors();
        $application = LeaveApplication::where('user_id', $employee->id)->firstOrFail();

        $this->actingAs($employee)->get(route('leave.form.pdf', $application))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $this->actingAs($employee)->get(route('leave.form.download', $application))
            ->assertOk()
            ->headers->get('Content-Disposition');

        $this->assertStringContainsString('.pdf', $disposition);
        $this->assertStringNotContainsString('.xlsx', $disposition);
    }

    public function test_a_word_file_is_refused_with_a_clear_message(): void
    {
        Storage::fake('local');
        $employee = $this->user();

        $this->file($employee, 'form.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertSessionHasErrors('leave_form');

        $this->assertDatabaseCount('leave_applications', 0);
    }

    public function test_the_same_days_cannot_be_filed_twice(): void
    {
        Storage::fake('local');
        Notification::fake();
        $employee = $this->user();

        $this->file($employee, 'a.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertSessionHasNoErrors();

        $this->file($employee, 'b.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', '2026-11-03', '2026-11-05')
            ->assertSessionHasErrors('date_from');

        $this->assertDatabaseCount('leave_applications', 1);

        // Once returned, the same days may be filed again.
        LeaveApplication::query()->update(['status' => 'dean_returned']);

        $this->file($employee, 'c.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------------
    // PDS review by year
    // ------------------------------------------------------------------

    public function test_reviewing_last_years_pds_acts_on_last_years_sheet(): void
    {
        Notification::fake();
        $hr = $this->user('admin');
        $employee = $this->user();
        $lastYear = now()->year - 1;

        $old = \App\Models\PdsSubmission::create([
            'user_id' => $employee->id, 'applicable_year' => $lastYear, 'status' => 'submitted',
        ]);
        $current = \App\Models\PdsSubmission::create([
            'user_id' => $employee->id, 'applicable_year' => now()->year, 'status' => 'submitted',
        ]);

        // The list for last year links to last year's sheet.
        $this->actingAs($hr)->get(route('admin.pds.index', ['year' => $lastYear]))
            ->assertSee(route('admin.pds.show', [$employee, 'year' => $lastYear]), false);

        // Its approve form carries the year...
        $this->actingAs($hr)->get(route('admin.pds.show', [$employee, 'year' => $lastYear]))
            ->assertSee('name="year" value="' . $lastYear . '"', false);

        // ...so the approval lands on it, not on this year's.
        $this->actingAs($hr)->post(route('admin.pds.approve', $employee), ['year' => $lastYear])
            ->assertRedirect(route('admin.pds.index', ['year' => $lastYear]));

        $this->assertSame('approved', $old->fresh()->status);
        $this->assertSame('submitted', $current->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Announcements
    // ------------------------------------------------------------------

    public function test_publishing_a_draft_announcement_notifies_staff(): void
    {
        Notification::fake();
        $hr = $this->user('admin');
        $employee = $this->user();

        $this->actingAs($hr)->post(route('admin.announcements.store'), [
            'title' => 'Flag ceremony', 'body' => 'Monday, 7:30.', 'is_published' => 0,
        ])->assertSessionHasNoErrors();

        Notification::assertNothingSent();

        $announcement = \App\Models\Announcement::firstOrFail();

        $this->actingAs($hr)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Flag ceremony', 'body' => 'Monday, 7:30.', 'is_published' => 1,
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($employee, \App\Notifications\AnnouncementPosted::class);
    }

    // ------------------------------------------------------------------
    // HR policies
    // ------------------------------------------------------------------

    private function policy(array $attributes = []): \App\Models\HrPolicy
    {
        return \App\Models\HrPolicy::create(array_merge([
            'title' => 'Policy ' . fake()->unique()->word(),
            'type' => 'text',
            'body' => '<p>Text</p>',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => User::where('role', 'admin')->value('id') ?? $this->user('admin')->id,
        ], $attributes));
    }

    public function test_a_read_memo_stops_counting_as_unread_on_the_dashboard(): void
    {
        $employee = $this->user();
        $memo = $this->policy(['requires_acknowledgment' => false]);

        $unread = fn () => app(\App\Services\DashboardService::class)->forEmployee($employee)['policiesUnread'];

        $this->assertSame(1, $unread());

        // Opening it is all a memo asks for. It used to stay "to read" for
        // good, because only acknowledgments were subtracted.
        $this->actingAs($employee)->get(route('policies.show', $memo))->assertOk();

        $this->assertSame(0, $unread());
    }

    public function test_expired_and_upcoming_policies_are_not_listed_or_acknowledged(): void
    {
        $employee = $this->user();
        $current = $this->policy(['title' => 'Current rules', 'requires_acknowledgment' => true]);
        $expired = $this->policy(['title' => 'Old rules', 'requires_acknowledgment' => true, 'expiry_date' => now()->subDay()]);
        $upcoming = $this->policy(['title' => 'Next year rules', 'effective_date' => now()->addMonth()]);

        $this->actingAs($employee)->get(route('policies.index'))
            ->assertOk()
            ->assertSee('Current rules')
            ->assertDontSee('Old rules')
            ->assertDontSee('Next year rules');

        $this->assertSame(1, app(\App\Services\DashboardService::class)->forEmployee($employee)['policiesUnread']);

        // Not in force yet, so not published to staff.
        $this->actingAs($employee)->get(route('policies.show', $upcoming))->assertNotFound();

        // A lapsed policy can still be read as history, but not acknowledged.
        $this->actingAs($employee)->get(route('policies.show', $expired))->assertOk();
        $this->actingAs($employee)->post(route('policies.acknowledge', $expired))->assertNotFound();
        $this->actingAs($employee)->post(route('policies.acknowledge', $current))->assertRedirect();
    }

    // ------------------------------------------------------------------
    // Employee-facing wording
    // ------------------------------------------------------------------

    public function test_the_campus_directors_leave_page_names_hr_not_the_dean(): void
    {
        $director = $this->user('campus_director');

        $this->actingAs($director)->get(route('leave.index'))
            ->assertOk()
            ->assertSee('It goes straight to the HR Administrator.')
            ->assertDontSee('straight to your Dean');
    }

    public function test_the_dashboard_greets_by_given_name(): void
    {
        $employee = $this->user('employee', ['name' => 'DELA CRUZ, Juan M.']);

        $this->actingAs($employee)->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(', Juan', false)
            ->assertDontSee(', Dela', false);
    }
}
