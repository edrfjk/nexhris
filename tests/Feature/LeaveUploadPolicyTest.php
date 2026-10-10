<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An uploaded leave form is held to the same type-and-date rules as the
 * on-screen form, and so is a corrected re-upload. 2026-10-05 is a Monday.
 */
class LeaveUploadPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 9, 1)->setTime(9, 0));

        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active', 'college_id' => $college->id]);
        $college->update(['dean_id' => $dean->id]);

        $this->employee = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $college->id]);
        LeaveBalance::create(['user_id' => $this->employee->id, 'vl_balance' => 15, 'sl_balance' => 15, 'service_balance' => 0]);
    }

    private function upload(array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->employee)->post(route('leave.store'), $data + [
            'leave_form' => UploadedFile::fake()->create('leave.pdf', 20, 'application/pdf'),
        ]);
    }

    private function holiday(string $date): void
    {
        Holiday::create(['name' => 'Test Holiday', 'date' => $date, 'category' => 'regular', 'scope' => 'national', 'excludes_from_leave' => true]);
    }

    public function test_a_valid_upload_is_filed_with_the_counted_days(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-09'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(5, $this->employee->leaveApplications()->sole()->days);
    }

    public function test_an_upload_does_not_charge_a_holiday(): void
    {
        $this->holiday('2026-10-07');

        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-09'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(4, $this->employee->leaveApplications()->sole()->days);
    }

    public function test_a_valid_online_filing_still_goes_through(): void
    {
        $this->actingAs($this->employee)->post(route('leave.apply.store'), [
            'leave_type' => 'VL', 'date_from' => '2026-10-19', 'date_to' => '2026-10-21',
            'details' => ['location' => 'within'], 'commutation' => 'not_requested',
        ])->assertRedirect(route('leave.index'))->assertSessionHasNoErrors();

        $this->assertEquals(3, $this->employee->leaveApplications()->sole()->days);
    }

    public function test_the_online_form_counts_and_names_holidays_the_same_way(): void
    {
        $this->holiday('2026-10-21');

        $this->actingAs($this->employee)->postJson(route('leave.apply.check'), [
            'leave_type' => 'VL', 'date_from' => '2026-10-19', 'date_to' => '2026-10-21',
        ])->assertOk()
            ->assertJsonPath('days', 2)
            ->assertJsonPath('holidays.0.date', '2026-10-21');

        $this->postJson(route('leave.apply.check'), [
            'leave_type' => 'VL', 'date_from' => '2026-10-21', 'date_to' => '2026-10-21',
        ])->assertJsonPath('errors.date_from', 'The selected dates contain no chargeable working days — they fall on weekends and holidays.');
    }

    public function test_an_unknown_leave_type_is_refused(): void
    {
        $this->upload(['leave_type' => 'NOPE', 'date_from' => '2026-10-05', 'date_to' => '2026-10-06'])
            ->assertSessionHasErrors('leave_type');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_more_days_than_the_type_allows_are_refused(): void
    {
        // Special Privilege Leave is up to three working days.
        $this->upload(['leave_type' => 'SPL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-08'])
            ->assertSessionHasErrors('date_to');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_the_yearly_allowance_counts_earlier_filings(): void
    {
        $this->upload(['leave_type' => 'SPL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-06'])->assertSessionHasNoErrors();

        $this->upload(['leave_type' => 'SPL', 'date_from' => '2026-11-02', 'date_to' => '2026-11-03'])
            ->assertSessionHasErrors('date_to');

        $this->assertSame(1, LeaveApplication::count());
    }

    public function test_a_once_a_year_type_cannot_be_filed_twice(): void
    {
        $this->upload(['leave_type' => 'SEL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-05'])->assertSessionHasNoErrors();

        $this->upload(['leave_type' => 'SEL', 'date_from' => '2026-11-02', 'date_to' => '2026-11-02'])
            ->assertSessionHasErrors('leave_type');
    }

    public function test_required_documents_are_flagged_without_blocking(): void
    {
        $this->upload(['leave_type' => 'ML', 'date_from' => '2026-10-05', 'date_to' => '2026-10-30'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'supporting documents'));
    }

    public function test_overlapping_dates_are_refused(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-07'])->assertSessionHasNoErrors();

        $this->upload(['leave_type' => 'SL', 'date_from' => '2026-10-07', 'date_to' => '2026-10-08'])
            ->assertSessionHasErrors('date_from');
    }

    public function test_a_backwards_date_range_is_refused(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-09', 'date_to' => '2026-10-05'])
            ->assertSessionHasErrors('date_to');
    }

    public function test_a_weekend_only_upload_is_refused(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-10', 'date_to' => '2026-10-11'])
            ->assertSessionHasErrors('date_from');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_a_holiday_only_upload_is_refused_with_a_clear_reason(): void
    {
        $this->holiday('2026-10-09');

        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-09', 'date_to' => '2026-10-11'])
            ->assertSessionHasErrors(['date_from' => 'The selected dates contain no chargeable working days — they fall on weekends and holidays.']);
    }

    public function test_a_days_figure_sent_with_the_upload_is_ignored(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-09', 'days' => 1])
            ->assertSessionHasNoErrors();

        $this->assertEquals(5, $this->employee->leaveApplications()->sole()->days);
    }

    public function test_a_corrected_reupload_is_checked_again_and_recounted(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-09'])->assertSessionHasNoErrors();
        $application = $this->employee->leaveApplications()->sole();
        $application->update(['status' => 'dean_returned', 'dean_status' => 'returned']);

        // A holiday proclaimed after the form was returned is honoured.
        $this->holiday('2026-10-07');

        $this->actingAs($this->employee)->post(route('leave.resubmit', $application), [
            'leave_form' => UploadedFile::fake()->create('fixed.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertEquals(4, $application->days);
    }

    public function test_a_reupload_that_now_overlaps_another_filing_is_refused(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-09'])->assertSessionHasNoErrors();
        $returned = $this->employee->leaveApplications()->sole();
        $returned->update(['status' => 'dean_returned', 'dean_status' => 'returned']);

        // Filed while the first was out for correction.
        $this->upload(['leave_type' => 'SL', 'date_from' => '2026-10-08', 'date_to' => '2026-10-08'])->assertSessionHasNoErrors();

        $this->actingAs($this->employee)->post(route('leave.resubmit', $returned), [
            'leave_form' => UploadedFile::fake()->create('fixed.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('date_from');

        $this->assertSame('dean_returned', $returned->fresh()->status);
    }

    public function test_a_refused_upload_leaves_no_file_behind(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-10', 'date_to' => '2026-10-11'])
            ->assertSessionHasErrors('date_from');

        $this->assertSame([], Storage::disk('local')->allFiles('leave-applications'));
    }

    public function test_a_refused_reupload_keeps_the_original_file(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-09'])->assertSessionHasNoErrors();
        $returned = $this->employee->leaveApplications()->sole();
        $returned->update(['status' => 'dean_returned']);
        $original = $returned->file_path;
        $this->upload(['leave_type' => 'SL', 'date_from' => '2026-10-08', 'date_to' => '2026-10-08'])->assertSessionHasNoErrors();

        $this->actingAs($this->employee)->post(route('leave.resubmit', $returned), [
            'leave_form' => UploadedFile::fake()->create('fixed.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('date_from');

        $this->assertSame($original, $returned->fresh()->file_path);
        Storage::disk('local')->assertExists($original);
        $this->assertCount(2, Storage::disk('local')->allFiles('leave-applications'));
    }

    public function test_someone_else_cannot_reupload_your_form(): void
    {
        $this->upload(['leave_type' => 'VL', 'date_from' => '2026-10-05', 'date_to' => '2026-10-06'])->assertSessionHasNoErrors();
        $application = $this->employee->leaveApplications()->sole();
        $application->update(['status' => 'dean_returned']);

        $other = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        $this->actingAs($other)->post(route('leave.resubmit', $application), [
            'leave_form' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_the_upload_form_explains_how_days_are_counted(): void
    {
        $this->actingAs($this->employee)->get(route('leave.index'))
            ->assertOk()
            ->assertSee('holidays HR has marked not charged', false);
    }
}
