<?php

namespace Tests\Feature;

use App\Mail\TwoFactorCodeMail;
use App\Models\Announcement;
use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\PdsSubmission;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Notifications\LeaveStageChanged;
use App\Notifications\PdsStatusChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every email the system sends wears the same letterhead.
 *
 * The shell lives in resources/views/vendor/mail, which is a published copy of
 * Laravel's. Re-running `vendor:publish --tag=laravel-mail --force` silently
 * restores the stock design — blue buttons, a Laravel logo and no institution
 * on it — and nothing else in the suite would notice. This is what notices.
 */
class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'employee'): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Dela Cruz, Juan M.',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'correct-horse-battery',
            'role' => $role,
            'status' => 'active',
            'college_id' => College::firstOrCreate(
                ['code' => 'CAS'], ['name' => 'College of Arts and Sciences'])->id,
        ]);
    }

    /**
     * Every email the system can send, rendered.
     *
     * @return array<string, string>
     */
    private function everyEmail(): array
    {
        $user = $this->person('dean');

        $leave = LeaveApplication::create([
            'user_id' => $user->id,
            'leave_type' => 'VL',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'days' => 2,
            'status' => 'submitted',
        ]);

        $pds = PdsSubmission::create([
            'user_id' => $user->id,
            'applicable_year' => now()->year,
            'status' => 'submitted',
        ]);

        $announcement = Announcement::create([
            'title' => 'Office closure',
            'body' => 'The HR Office is closed on Friday.',
            'created_by' => $user->id,
        ]);

        return [
            'two-factor code' => (new TwoFactorCodeMail($user, '482913', 10))->render(),

            'password reset' => (new ResetPassword('a-token'))->toMail($user)->render(),

            'leave stage' => (new LeaveStageChanged(
                $leave, 'Leave application awaiting your approval',
                'A leave application has reached you for review.',
                'Review the application', url('/admin/leave/review/' . $leave->id),
            ))->toMail($user)->render(),

            'PDS status' => (new PdsStatusChanged(
                $pds, 'Your Personal Data Sheet was returned',
                'Correct it and submit again.',
                'Open my Personal Data Sheet', url('/pds'),
            ))->toMail($user)->render(),

            'announcement' => (new AnnouncementPosted($announcement))->toMail($user)->render(),
        ];
    }

    public function test_every_email_carries_the_institutional_letterhead(): void
    {
        foreach ($this->everyEmail() as $which => $html) {
            $this->assertStringContainsString('Republic of the Philippines', $html, "$which has no letterhead");
            $this->assertStringContainsString('Ilocos Sur Polytechnic State College', $html, "$which does not name the institution");
            $this->assertStringContainsString('Tagudin Campus', $html, "$which does not name the campus");
        }
    }

    public function test_every_email_uses_the_seal_colours(): void
    {
        foreach ($this->everyEmail() as $which => $html) {
            // Maroon masthead, gold rule beneath it.
            $this->assertStringContainsString('#780000', $html, "$which is not in the campus maroon");
            $this->assertStringContainsString('#f0dc00', $html, "$which is missing the gold rule");
        }
    }

    public function test_no_email_carries_laravel_branding(): void
    {
        foreach ($this->everyEmail() as $which => $html) {
            $this->assertStringNotContainsStringIgnoringCase('laravel', $html, "$which still shows Laravel branding");

            // The stock button and body colours, which would mean the vendor
            // views were republished over ours.
            foreach (['#3869d4', '#3490dc', '#2d3748', '#edf2f7'] as $stock) {
                $this->assertStringNotContainsStringIgnoringCase($stock, $html, "$which uses the stock $stock");
            }
        }
    }

    public function test_every_email_closes_with_the_office(): void
    {
        foreach ($this->everyEmail() as $which => $html) {
            $this->assertStringContainsString('Human Resource Management Office', $html, "$which does not say who sent it");
            $this->assertStringContainsString('do not reply', $html, "$which invites a reply it cannot receive");
        }
    }

    /** The one piece of the code email that must survive intact. */
    public function test_the_verification_code_is_shown_as_issued(): void
    {
        $html = (new TwoFactorCodeMail($this->person('admin'), '482913', 10))->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('code-value', $html);
        $this->assertStringContainsString('10 minutes', $html);
    }

    /** Laravel's own reset notice, rewritten so it reads like the rest. */
    public function test_the_password_reset_says_who_sent_it(): void
    {
        $html = (new ResetPassword('a-token'))->toMail($this->person())->render();

        $this->assertStringContainsString('NexHRIS', $html);
        $this->assertStringNotContainsString('we received a password reset request', $html);
    }
}
