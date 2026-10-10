<?php

namespace Tests\Integration;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * DATA-02 on MariaDB: one employee, two submissions in two processes at the
 * same moment. The first holds its transaction open after inserting; the
 * second arrives during that window. Only the rules decide what is filed.
 */
class ConcurrentFilingTest extends MariaDbTestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active', 'college_id' => $college->id]);
        $college->update(['dean_id' => $dean->id]);
        $this->employee = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $college->id]);
        LeaveBalance::create(['user_id' => $this->employee->id, 'vl_balance' => 15, 'sl_balance' => 15]);
    }

    /** A Monday far enough ahead that no advance-filing rule interferes. */
    private function monday(): Carbon
    {
        return now()->addMonths(2)->startOfWeek();
    }

    private function job(string $action, string $type, Carbon $from, Carbon $to, array $extra = []): array
    {
        return $extra + [
            'action' => $action,
            'reviewer' => $this->employee->id,
            'input' => ['leave_type' => $type, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
        ];
    }

    public function test_two_overlapping_uploads_at_once_file_one_form(): void
    {
        $from = $this->monday();

        [$first, $second] = $this->race([
            $this->job('file', 'VL', $from, $from->copy()->addDays(2), ['hold' => 2]),
            $this->job('file', 'SL', $from->copy()->addDay(), $from->copy()->addDays(3), ['delay' => 0.5]),
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('refused', $second['outcome'], json_encode($second));
        $this->assertStringContainsString('already filed leave covering', $second['message']);
        $this->assertGreaterThan(1.0, $second['waited'], 'The second filing must have waited on the lock.');

        $this->assertSame(1, LeaveApplication::where('user_id', $this->employee->id)->count());
    }

    public function test_two_overlapping_online_filings_at_once_file_one_form(): void
    {
        $from = $this->monday();

        [$first, $second] = $this->race([
            $this->job('file_online', 'VL', $from, $from->copy()->addDay(), ['hold' => 2]),
            $this->job('file_online', 'VL', $from, $from->copy()->addDay(), ['delay' => 0.5]),
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('refused', $second['outcome'], json_encode($second));
        $this->assertSame(1, LeaveApplication::where('user_id', $this->employee->id)->count());
    }

    public function test_the_yearly_allowance_holds_under_simultaneous_filings(): void
    {
        // Special Privilege Leave is three days a year. Two filings of two
        // days each do not overlap, so only the yearly limit can stop the
        // second — and only if it sees the first.
        $from = $this->monday();
        $later = $from->copy()->addWeek();
        $this->assertSame($from->year, $later->copy()->addDay()->year, 'Both filings must fall in one year.');

        [$first, $second] = $this->race([
            $this->job('file', 'SPL', $from, $from->copy()->addDay(), ['hold' => 2]),
            $this->job('file', 'SPL', $later, $later->copy()->addDay(), ['delay' => 0.5]),
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('refused', $second['outcome'], json_encode($second));
        $this->assertStringContainsString('3 days a year', $second['message']);
        $this->assertEquals(2, LeaveApplication::where('user_id', $this->employee->id)->sum('days'));
    }

    public function test_different_employees_are_not_held_up_by_each_other(): void
    {
        $other = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $this->employee->college_id]);
        LeaveBalance::create(['user_id' => $other->id, 'vl_balance' => 15, 'sl_balance' => 15]);
        $from = $this->monday();

        [$first, $second] = $this->race([
            $this->job('file', 'VL', $from, $from->copy()->addDay(), ['hold' => 2]),
            ['reviewer' => $other->id] + $this->job('file', 'VL', $from, $from->copy()->addDay(), ['delay' => 0.5]),
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('ok', $second['outcome'], json_encode($second));
        $this->assertLessThan(1.0, $second['waited'], 'Another employee must not wait on this one\'s lock.');
        $this->assertSame(2, LeaveApplication::count());
    }
}
