<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every HR list screen filters through one shared bar. These cover the parts
 * that were actually wrong before it existed: filters with no visible label,
 * and applied filters reported back as raw ids.
 */
class FilterBarTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private College $cas;
    private Department $bsit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->cas = College::where('code', 'CAS')->firstOrFail();
        $this->bsit = Department::firstOrCreate(
            ['college_id' => $this->cas->id, 'code' => 'BSIT'],
            ['name' => 'Bachelor of Science in Information Technology'],
        );
    }

    public static function listScreens(): array
    {
        return [
            'employees' => ['admin.employees.index'],
            'pds review' => ['admin.pds.index'],
            'ledger cards' => ['admin.leave.index'],
            'leave reviews' => ['admin.leave.review.index'],
            'activity log' => ['admin.activity-logs.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listScreens')]
    public function test_each_list_screen_uses_the_shared_filter_bar(string $route): void
    {
        // They used three different filter designs before; the complaint was
        // that they did not look like one system.
        $this->actingAs($this->hr)
            ->get(route($route))
            ->assertOk()
            ->assertSee('filter-bar', false)
            ->assertSee('Apply filters');
    }

    public function test_an_applied_filter_is_named_not_shown_as_an_id(): void
    {
        $response = $this->actingAs($this->hr)
            ->get(route('admin.employees.index', ['college' => $this->cas->id]))
            ->assertOk();

        // The chip used to print "College: 3".
        $response->assertSee('College of Arts and Sciences')
            ->assertDontSee('College: ' . $this->cas->id);
    }

    public function test_each_applied_filter_can_be_dropped_on_its_own(): void
    {
        $this->actingAs($this->hr)
            ->get(route('admin.employees.index', [
                'college' => $this->cas->id,
                'status' => 'active',
            ]))
            ->assertOk()
            // Removing the college must keep the status, and the other way round.
            ->assertSee(route('admin.employees.index', ['status' => 'active']), false)
            ->assertSee(route('admin.employees.index', ['college' => $this->cas->id]), false);
    }

    public function test_the_bar_stays_quiet_when_nothing_is_applied(): void
    {
        $this->actingAs($this->hr)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertDontSee('Showing:')
            ->assertDontSee('Clear all');
    }

    public function test_the_activity_log_dates_are_labelled_on_the_page(): void
    {
        // These were two bare date boxes told apart only by a tooltip.
        $this->actingAs($this->hr)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('From date')
            ->assertSee('To date');
    }

    public function test_a_date_range_reads_back_as_dates_not_raw_input(): void
    {
        $this->actingAs($this->hr)
            ->get(route('admin.activity-logs.index', [
                'from' => '2026-01-15',
                'to' => '2026-02-20',
            ]))
            ->assertOk()
            ->assertSee('Jan 15, 2026')
            ->assertSee('Feb 20, 2026');
    }

    public function test_the_leave_review_filters_are_labelled(): void
    {
        $this->actingAs($this->hr)
            ->get(route('admin.leave.review.index'))
            ->assertOk()
            ->assertSee('Leave type')
            ->assertSee('College / Office')
            ->assertSee('Department')
            ->assertSee('Order');
    }

    public function test_a_dean_is_not_offered_a_college_filter(): void
    {
        $dean = User::factory()->create([
            'role' => 'dean', 'status' => 'active', 'college_id' => $this->cas->id,
        ]);
        $this->cas->update(['dean_id' => $dean->id]);

        // A Dean only ever sees one college, so the box would have one option.
        $this->actingAs($dean)
            ->get(route('admin.leave.review.index'))
            ->assertOk()
            ->assertDontSee('All colleges / offices');
    }
}
