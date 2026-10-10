<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use App\Services\Leave\WorkingDayCalculator;
use App\Services\LeaveForm\LeavePolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chargeable days under the holiday calendar. 2026-10-05 is a Monday.
 */
class HolidayWorkingDaysTest extends TestCase
{
    use RefreshDatabase;

    private WorkingDayCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new WorkingDayCalculator();
        $this->travelTo(Carbon::parse('2026-09-01 09:00'));
    }

    private function holiday(string $date, string $category = 'regular', ?bool $excluded = null, ?string $name = null): Holiday
    {
        return Holiday::create([
            'name' => $name ?? "Holiday {$date}",
            'date' => $date,
            'category' => $category,
            'scope' => $category === 'special_local' ? 'local' : 'national',
            'locality' => $category === 'special_local' ? 'Tagudin, Ilocos Sur' : null,
            'excludes_from_leave' => $excluded ?? Holiday::EXCLUDED_BY_DEFAULT[$category],
        ]);
    }

    private function days(string $from, string $to, bool $holidays = true): int
    {
        return $this->calc->days(Carbon::parse($from), Carbon::parse($to), $holidays);
    }

    public function test_a_monday_to_friday_range_counts_five_days(): void
    {
        $this->assertSame(5, $this->days('2026-10-05', '2026-10-09'));
    }

    public function test_a_weekend_only_range_has_no_working_days(): void
    {
        $this->assertSame(0, $this->days('2026-10-10', '2026-10-11'));
    }

    public function test_a_weekday_holiday_is_not_counted(): void
    {
        $this->holiday('2026-10-07');

        $result = $this->calc->breakdown(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-09'));

        $this->assertSame(4, $result['days']);
        $this->assertSame([['date' => '2026-10-07', 'name' => 'Holiday 2026-10-07']], $result['holidays']);
    }

    public function test_consecutive_holidays_are_each_left_out(): void
    {
        $this->holiday('2026-12-24', 'special_non_working');
        $this->holiday('2026-12-25');

        $this->assertSame(3, $this->days('2026-12-21', '2026-12-25'));
    }

    public function test_a_holiday_on_saturday_changes_nothing(): void
    {
        $this->holiday('2026-10-10');

        $result = $this->calc->breakdown(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-11'));

        $this->assertSame(5, $result['days']);
        $this->assertSame([], $result['holidays'], 'A weekend holiday is not reported as excluded.');
    }

    public function test_a_holiday_on_sunday_changes_nothing(): void
    {
        $this->holiday('2026-10-11');

        $this->assertSame(5, $this->days('2026-10-05', '2026-10-11'));
    }

    public function test_a_range_across_the_new_year(): void
    {
        $this->holiday('2026-12-30', name: 'Rizal Day');
        $this->holiday('2026-12-31', 'special_non_working', name: 'Last Day of the Year');
        $this->holiday('2027-01-01', name: "New Year's Day");

        // Dec 28, 29, 30, 31, Jan 1, 4, 5 are weekdays; three are holidays.
        $this->assertSame(4, $this->days('2026-12-28', '2027-01-05'));
    }

    public function test_a_leap_day_is_a_working_day(): void
    {
        // Monday 28 Feb 2028 to Wednesday 1 Mar 2028.
        $this->assertSame(3, $this->days('2028-02-28', '2028-03-01'));
    }

    public function test_a_single_day_holiday_filing_has_no_chargeable_days(): void
    {
        $this->holiday('2026-10-07');

        $this->assertSame(0, $this->days('2026-10-07', '2026-10-07'));
    }

    public function test_a_range_of_only_weekends_and_holidays_has_no_chargeable_days(): void
    {
        $this->holiday('2026-10-09');

        $this->assertSame(0, $this->days('2026-10-09', '2026-10-11'));
    }

    public function test_an_applicable_local_holiday_is_not_counted(): void
    {
        $this->holiday('2026-10-07', 'special_local');

        $this->assertSame(4, $this->days('2026-10-05', '2026-10-09'));
    }

    public function test_a_special_working_day_is_still_charged(): void
    {
        $this->holiday('2026-10-07', 'special_working');

        $this->assertSame(5, $this->days('2026-10-05', '2026-10-09'));
    }

    public function test_each_category_is_treated_by_its_own_flag(): void
    {
        $this->assertTrue(Holiday::EXCLUDED_BY_DEFAULT['regular']);
        $this->assertTrue(Holiday::EXCLUDED_BY_DEFAULT['special_non_working']);
        $this->assertTrue(Holiday::EXCLUDED_BY_DEFAULT['special_local']);
        $this->assertFalse(Holiday::EXCLUDED_BY_DEFAULT['special_working']);
        $this->assertFalse(Holiday::EXCLUDED_BY_DEFAULT['religious'], 'HR decides each religious holiday.');

        // A religious holiday HR marked as applying is excluded; one it did not is charged.
        $this->holiday('2026-10-06', 'religious', true, 'Applies');
        $this->holiday('2026-10-08', 'religious', false, 'Does not apply');

        $this->assertSame(4, $this->days('2026-10-05', '2026-10-09'));
    }

    public function test_two_holidays_on_one_date_take_one_day_off(): void
    {
        $this->holiday('2026-10-07', name: 'First');
        $this->holiday('2026-10-07', 'special_local', name: 'Second');

        $result = $this->calc->breakdown(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-09'));

        $this->assertSame(4, $result['days']);
        $this->assertSame('First / Second', $result['holidays'][0]['name']);
    }

    public function test_a_reversed_range_is_never_negative(): void
    {
        $this->assertSame(0, $this->days('2026-10-09', '2026-10-05'));
    }

    public function test_holidays_can_be_left_out_of_the_count(): void
    {
        $this->holiday('2026-10-07');

        $this->assertSame(5, $this->days('2026-10-05', '2026-10-09', holidays: false));
    }

    public function test_special_privilege_leave_counts_its_three_days_without_holidays(): void
    {
        $this->holiday('2026-10-07');
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        // Mon to Thu with a Wednesday holiday is three working days: within the limit.
        $result = app(LeavePolicy::class)->check(
            ['leave_type' => 'SPL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-08'],
            $employee, mode: LeavePolicy::UPLOAD,
        );

        $this->assertSame(3.0, $result['days']);
        $this->assertArrayNotHasKey('date_to', $result['errors']);
    }

    public function test_calamity_leave_counts_five_working_days_without_holidays(): void
    {
        $this->holiday('2026-10-07');
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        // Mon 5 to Mon 12 Oct: six weekdays, one a holiday, so five days — the limit, not over it.
        $result = app(LeavePolicy::class)->check(
            ['leave_type' => 'SEL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-12'],
            $employee, mode: LeavePolicy::UPLOAD,
        );

        $this->assertSame(5.0, $result['days']);
        $this->assertArrayNotHasKey('date_to', $result['errors']);
    }

    public function test_a_friday_is_a_working_day(): void
    {
        // An asynchronous Friday is still a working day, so it is charged.
        $this->assertSame(1, $this->days('2026-10-09', '2026-10-09'));
        $this->assertSame(2, $this->days('2026-10-08', '2026-10-11'));
    }

    public function test_only_the_approved_working_day_types_leave_holidays_out(): void
    {
        $this->holiday('2026-10-07');
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $policy = app(LeavePolicy::class);
        $dates = ['date_from' => '2026-10-05', 'date_to' => '2026-10-09'];

        $this->assertSame(4.0, $policy->check(['leave_type' => 'VL'] + $dates, $employee, mode: LeavePolicy::UPLOAD)['days']);
        $this->assertSame(4.0, $policy->check(['leave_type' => 'SL'] + $dates, $employee, mode: LeavePolicy::UPLOAD)['days']);
        $this->assertSame(4.0, $policy->check(['leave_type' => 'FL'] + $dates, $employee, mode: LeavePolicy::UPLOAD)['days']);
        $this->assertSame(4.0, $policy->check(['leave_type' => 'SOLO'] + $dates, $employee, mode: LeavePolicy::UPLOAD)['days']);
        // Types HR has not confirmed keep Monday to Friday.
        foreach (['PL', 'VAWC', 'SLBW', 'ADOPT', 'STUDY', 'RL', 'SERVICE', 'WELLNESS'] as $type) {
            $this->assertSame(5.0, $policy->check(['leave_type' => $type] + $dates, $employee, mode: LeavePolicy::UPLOAD)['days'], $type);
        }
        // Maternity leave stays in calendar days.
        $this->assertSame(5.0, $policy->check(['leave_type' => 'ML'] + $dates, $employee, mode: LeavePolicy::UPLOAD)['days']);
        $this->assertSame(7.0, $policy->check(['leave_type' => 'ML', 'date_from' => '2026-10-05', 'date_to' => '2026-10-11'], $employee, mode: LeavePolicy::UPLOAD)['days']);
    }
}
