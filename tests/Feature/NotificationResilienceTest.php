<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Notifications\LeaveStageChanged;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A dead mail server must not stop HR approving leave.
 *
 * Notifications go to the in-app bell and to email, and nothing is queued
 * because shared hosting has no worker to run a queue. So the mail is sent
 * inside the request, and an unreachable SMTP host throws right there —
 * turning an approval into a 500 after the record had already been updated.
 *
 * Gmail's SMTP is exactly that kind of dependency: a rate limit, a blocked
 * port, an expired app password.
 */
class NotificationResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function pointMailAtNothing(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 1,
        ]);
    }

    private function application(): LeaveApplication
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'college_id' => College::where('code', 'CAS')->value('id'),
        ]);

        return LeaveApplication::create([
            'user_id' => $employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'submitted',
        ]);
    }

    public function test_a_failing_mail_server_does_not_throw(): void
    {
        $this->pointMailAtNothing();

        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active']);

        Notifier::send($dean, new LeaveStageChanged(
            $this->application(), 'Subject', 'Body', 'Open', route('leave.index'), 'info',
        ));

        // Reaching here at all is the assertion: unguarded, this throws a
        // Symfony TransportException and the caller returns a 500.
        $this->assertTrue(true);
    }

    public function test_the_in_app_notification_still_arrives_when_email_fails(): void
    {
        $this->pointMailAtNothing();

        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active']);

        Notifier::send($dean, new LeaveStageChanged(
            $this->application(), 'Subject', 'Body', 'Open', route('leave.index'), 'info',
        ));

        $this->assertSame(
            1,
            $dean->notifications()->count(),
            'the bell should still show the notification even with no mail server',
        );
    }
}
