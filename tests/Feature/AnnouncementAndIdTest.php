<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\College;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnnouncementAndIdTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?int $collegeId = null, array $overrides = []): User
    {
        return User::create(array_merge([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst($role) . ' ' . fake()->unique()->numberBetween(1, 999),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'position' => 'Instructor I',
            'college_id' => $collegeId ?? College::where('code', 'CAS')->value('id'),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Announcements
    // ------------------------------------------------------------------

    public function test_hr_posts_an_announcement_and_everyone_is_notified(): void
    {
        Notification::fake();

        $hr = $this->user('admin');
        $employee = $this->user('employee');
        $dean = $this->user('dean');

        $this->actingAs($hr)->post(route('admin.announcements.store'), [
            'title' => 'Holiday schedule',
            'body' => 'The campus is closed on 30 December.',
            'is_published' => 1,
            'notify' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', ['title' => 'Holiday schedule', 'is_published' => true]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'announcement.posted']);

        Notification::assertSentTo($employee, AnnouncementPosted::class);
        Notification::assertSentTo($dean, AnnouncementPosted::class);
    }

    public function test_a_college_announcement_only_reaches_that_college(): void
    {
        Notification::fake();

        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $hr = $this->user('admin', $cas->id);
        $inCas = $this->user('employee', $cas->id);
        $inCte = $this->user('employee', $cte->id);

        $this->actingAs($hr)->post(route('admin.announcements.store'), [
            'title' => 'CAS faculty meeting',
            'body' => 'Friday, 3pm.',
            'college_id' => $cas->id,
            'is_published' => 1,
            'notify' => 1,
        ])->assertRedirect();

        Notification::assertSentTo($inCas, AnnouncementPosted::class);
        Notification::assertNotSentTo($inCte, AnnouncementPosted::class);
    }

    public function test_the_feed_shows_campus_wide_and_own_college_notices_only(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $hr = $this->user('admin');

        Announcement::create(['title' => 'Campus Wide Notice', 'body' => 'x',
            'is_published' => true, 'published_at' => now(), 'posted_by' => $hr->id]);
        Announcement::create(['title' => 'CAS Only Notice', 'body' => 'x', 'college_id' => $cas->id,
            'is_published' => true, 'published_at' => now(), 'posted_by' => $hr->id]);
        Announcement::create(['title' => 'CTE Only Notice', 'body' => 'x', 'college_id' => $cte->id,
            'is_published' => true, 'published_at' => now(), 'posted_by' => $hr->id]);

        $this->actingAs($this->user('employee', $cas->id))
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Campus Wide Notice')
            ->assertSee('CAS Only Notice')
            ->assertDontSee('CTE Only Notice');
    }

    public function test_an_unpublished_announcement_stays_off_the_feed(): void
    {
        $hr = $this->user('admin');

        Announcement::create(['title' => 'Draft Notice', 'body' => 'x',
            'is_published' => false, 'posted_by' => $hr->id]);

        $this->actingAs($this->user('employee'))
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Draft Notice');
    }

    public function test_only_hr_manages_announcements(): void
    {
        foreach (['dean', 'campus_director', 'employee'] as $role) {
            $this->actingAs($this->user($role))
                ->post(route('admin.announcements.store'), ['title' => 'Nope', 'body' => 'x'])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('announcements', 0);
    }

    // ------------------------------------------------------------------
    // Digital ID + public verification
    // ------------------------------------------------------------------

    public function test_every_account_gets_a_verification_token(): void
    {
        $employee = $this->user('employee');

        $this->assertNotNull($employee->fresh()->verification_token);
    }

    public function test_the_id_card_renders_a_scannable_qr(): void
    {
        $employee = $this->user('employee');

        $html = $this->actingAs($employee)->get(route('my-id.show'))->assertOk()->getContent();

        $this->assertStringContainsString('<svg', $html, 'The QR code should render as inline SVG.');
        $this->assertStringContainsString('Scan to verify', $html);
    }

    public function test_the_verification_page_is_public_and_shows_only_safe_fields(): void
    {
        $employee = $this->user('employee', null, [
            'contact_number' => '09171234567',
        ]);

        // No sign-in.
        $response = $this->get(route('verify.show', $employee->verification_token));

        $response->assertOk()
            ->assertSee($employee->name)
            ->assertSee('Instructor I')
            ->assertSee('Verified');

        // Nothing that could be used against the person.
        $response->assertDontSee($employee->email)
            ->assertDontSee('09171234567')
            ->assertDontSee($employee->employee_number);
    }

    public function test_an_inactive_account_verifies_as_not_active(): void
    {
        $employee = $this->user('employee', null, ['status' => 'inactive']);

        $this->get(route('verify.show', $employee->verification_token))
            ->assertOk()
            ->assertSee('Not an active employee');
    }

    public function test_an_unknown_token_reveals_nothing(): void
    {
        $this->user('employee', null, ['name' => 'Secret Person']);

        $this->get(route('verify.show', 'not-a-real-token'))
            ->assertNotFound()
            ->assertSee('Could not verify this ID')
            ->assertDontSee('Secret Person');
    }

    public function test_the_verification_page_cannot_be_addressed_by_employee_id(): void
    {
        $employee = $this->user('employee', null, ['name' => 'Enumerable Person']);

        // Counting upwards must not surface anybody.
        $this->get(route('verify.show', (string) $employee->id))
            ->assertNotFound()
            ->assertDontSee('Enumerable Person');
    }
}
