<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4: markup the browser audit relied on, kept from regressing.
 * Rendered-page behaviour (focus, overflow, double submission) was checked in
 * a real browser; these tests pin the server-rendered half of it.
 */
class UiAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function holiday(array $overrides = []): Holiday
    {
        return Holiday::create($overrides + [
            'name' => 'Independence Day', 'date' => now()->year . '-06-12', 'category' => 'regular',
            'scope' => 'national', 'excludes_from_leave' => true,
        ]);
    }

    public function test_every_page_has_a_skip_link_to_the_main_content(): void
    {
        $this->actingAs($this->hr())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<a href="#main-content" class="skip-link">Skip to main content</a>', false)
            ->assertSee('<main id="main-content" tabindex="-1"', false);
    }

    public function test_the_phone_sidebar_is_inert_while_closed_and_can_be_closed_from_inside(): void
    {
        $this->actingAs($this->hr())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(':inert="! desktop && ! sidebarOpen"', false)
            ->assertSee('aria-label="Main navigation"', false)
            ->assertSee(":aria-label=\"sidebarOpen ? 'Close the navigation menu' : 'Open the navigation menu'\"", false)
            ->assertSee('@click="sidebarOpen = false"', false);
    }

    public function test_feedback_messages_are_announced(): void
    {
        $hr = $this->hr();

        $this->actingAs($hr)->withSession(['success' => 'Saved.'])->get(route('admin.dashboard'))
            ->assertSee('data-flash role="status"', false);
        $this->withSession(['warning' => 'Careful.'])->get(route('admin.dashboard'))
            ->assertSee('role="alert" class="alert alert-warning', false);
        $this->withSession(['error' => 'Failed.'])->get(route('admin.dashboard'))
            ->assertSee('role="alert" class="alert alert-error', false);
    }

    public function test_label_less_filter_controls_have_accessible_names(): void
    {
        $hr = $this->hr();

        $this->actingAs($hr)->get(route('admin.dashboard'))->assertSee('aria-label="Find an employee by name or number"', false);
        $this->get(route('admin.colleges.index'))
            ->assertSee('aria-label="Search colleges by name or code"', false)
            ->assertSee('aria-label="Show colleges"', false);
        $this->get(route('admin.leave.calendar'))
            ->assertSee('aria-label="Month"', false)
            ->assertSee('aria-label="College"', false);
    }

    public function test_holidays_have_a_phone_layout_with_named_actions(): void
    {
        $this->holiday();

        $this->actingAs($this->hr())->get(route('admin.holidays.index'))
            ->assertOk()
            // Phones: stacked list. Wider screens: the table, in a positioned
            // scroller so screen-reader-only text cannot widen the page.
            ->assertSee('<ul class="sm:hidden divide-y divide-sand-200"', false)
            ->assertSee('<div class="hidden sm:block relative overflow-x-auto">', false)
            ->assertSee('Edit<span class="sr-only"> Independence Day</span>', false)
            ->assertSee('Remove<span class="sr-only"> Independence Day</span>', false);
    }

    public function test_dialog_forms_say_which_dialog_they_came_from(): void
    {
        $holiday = $this->holiday();
        $hr = $this->hr();

        $this->actingAs($hr)->get(route('admin.holidays.index'))
            ->assertSee('name="_dialog" value="add-holiday"', false)
            ->assertSee('name="_dialog" value="edit-holiday-' . $holiday->id . '"', false);
        $this->get(route('admin.announcements.index'))->assertSee('name="_dialog" value="new-announcement"', false);
        $this->get(route('admin.colleges.index'))->assertSee('name="_dialog" value="add-college"', false);
    }

    public function test_a_refused_dialog_is_reopened_with_its_input_but_never_a_password(): void
    {
        $hr = $this->hr();

        // A duplicate holiday: refused by the server.
        $this->holiday();
        $this->actingAs($hr)->from(route('admin.holidays.index'))->post(route('admin.holidays.store'), [
            '_dialog' => 'add-holiday', 'name' => 'Independence Day', 'date' => now()->year . '-06-12',
            'category' => 'regular', 'scope' => 'national', 'source' => 'Typed text',
        ])->assertSessionHasErrors('name');

        $page = $this->get(route('admin.holidays.index'))->assertOk();
        $page->assertSee('window.NexDialogState', false)
            ->assertSee('"id":"add-holiday"', false)
            ->assertSee('"source":"Typed text"', false)
            ->assertSee('already on the calendar', false);

        // Passwords are never echoed back into the page.
        $this->withSession(['_old_input' => ['_dialog' => 'add-holiday', 'password' => 'Secret123', 'current_password' => 'Old123']])
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['name' => ['x']]))])
            ->get(route('admin.holidays.index'))
            ->assertDontSee('Secret123')
            ->assertDontSee('Old123');
    }

    public function test_failed_values_stay_in_their_own_dialog(): void
    {
        $existing = $this->holiday();
        $hr = $this->hr();

        $this->actingAs($hr)->from(route('admin.holidays.index'))->post(route('admin.holidays.store'), [
            '_dialog' => 'add-holiday', 'name' => 'Independence Day', 'date' => now()->year . '-06-12',
            'category' => 'regular', 'scope' => 'national', 'source' => 'Failed attempt',
        ]);

        $html = $this->get(route('admin.holidays.index'))->getContent();
        $edit = substr($html, strpos($html, 'id="edit-holiday-' . $existing->id . '"'));
        $edit = substr($edit, 0, strpos($edit, '</dialog>'));
        $add = substr($html, strpos($html, '<dialog id="add-holiday"'));
        $add = substr($add, 0, strpos($add, '</dialog>'));

        $this->assertStringContainsString('value="Failed attempt"', $add);
        $this->assertStringNotContainsString('Failed attempt', $edit);
    }

    public function test_the_unticked_not_charged_box_stays_unticked_after_a_refusal(): void
    {
        $this->holiday();
        $hr = $this->hr();

        $this->actingAs($hr)->from(route('admin.holidays.index'))->post(route('admin.holidays.store'), [
            '_dialog' => 'add-holiday', 'name' => 'Independence Day', 'date' => now()->year . '-06-12',
            'category' => 'regular', 'scope' => 'national',
            // excludes_from_leave unticked: not sent at all.
        ]);

        $html = $this->get(route('admin.holidays.index'))->getContent();
        $add = substr($html, strpos($html, '<dialog id="add-holiday"'));
        $add = substr($add, 0, strpos($add, '</dialog>'));
        $this->assertStringContainsString('excluded: false', $add);
    }

    public function test_the_shared_form_script_guards_against_double_submission(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $forms = file_get_contents(resource_path('js/forms.js'));

        $this->assertStringContainsString("import './forms';", $app);
        // One submission per form, skipped for GET forms, cancelled submits and new-tab targets.
        $this->assertStringContainsString("form.hasAttribute('data-submitting')", $forms);
        $this->assertStringContainsString("=== 'get') return;", $forms);
        $this->assertStringContainsString('event.defaultPrevented', $forms);
        $this->assertStringContainsString("target !== '_self'", $forms);
        // Buttons are disabled on the next tick so the clicked button's name/value is still sent.
        $this->assertMatchesRegularExpression('/setTimeout\(\(\) => \{\s*form\.querySelectorAll\(\'\[type="submit"\]/', $forms);
        // Restored after Back.
        $this->assertStringContainsString("addEventListener('pageshow'", $forms);
    }

    public function test_long_status_badges_may_wrap(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/\.badge \{([^}]*)\}/', $css, $badge);
        $this->assertStringNotContainsString('whitespace-nowrap', $badge[1]);
        $this->assertStringContainsString('max-w-full', $badge[1]);
    }

    public function test_the_posting_form_explains_adjustments_in_words(): void
    {
        // The adjustment field and its rule are labelled, not only colour-coded.
        $view = file_get_contents(resource_path('views/admin/leave/review/show.blade.php'));

        $this->assertStringContainsString('Reason for an adjustment', $view);
        $this->assertStringContainsString('These figures differ from the approved form, and I am recording the adjustment.', $view);
    }
}
