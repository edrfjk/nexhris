<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveForm\CsForm6;
use App\Services\LeaveForm\CsForm6Repairs;
use App\Services\LeaveForm\LeaveFormDocuments;
use App\Services\LeaveForm\LeavePolicy;
use App\Services\Xlsx\TemplateFiller;
use App\Support\Leave\LeaveTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * CS Form No. 6 filled in on screen: checked against page 2 of the form,
 * printed into the official workbook, and kept current as the Dean, HR and
 * the Campus Director decide.
 */
class LeaveOnlineFormTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $dean;

    private User $hr;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
        config(['pdf.renderer' => 'php']);

        // A Monday, so "working days" are easy to count by hand.
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));

        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);

        $this->employee = $this->person('employee', [
            'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
            'name' => 'Dela Cruz, Juan S.', 'position' => 'Instructor I', 'college_id' => $college->id,
        ]);
        $this->dean = $this->person('dean', [
            'first_name' => 'Maria', 'middle_name' => 'Lopez', 'last_name' => 'Reyes',
            'name' => 'Reyes, Maria L.', 'college_id' => $college->id,
        ]);
        $college->update(['dean_id' => $this->dean->id]);
        $this->hr = $this->person('admin');
        $this->director = $this->person('campus_director');

        LeaveBalance::create(['user_id' => $this->employee->id, 'vl_balance' => 10, 'sl_balance' => 4, 'service_balance' => 2]);
    }

    private function person(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'status' => 'active'], $attributes));
    }

    private function vacation(array $overrides = []): array
    {
        return array_replace_recursive([
            'leave_type' => 'VL',
            'date_from' => '2026-10-19',
            'date_to' => '2026-10-21',
            'details' => ['location' => 'within', 'location_specify' => 'Baguio City'],
            'commutation' => 'not_requested',
            'salary' => '32,870.00',
        ], $overrides);
    }

    private function file(array $input): LeaveApplication
    {
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $input)
            ->assertRedirect(route('leave.index'))
            ->assertSessionHasNoErrors();

        return LeaveApplication::where('user_id', $this->employee->id)->latest('id')->firstOrFail();
    }

    /** The printed workbook's parts, for looking inside. */
    private function printed(LeaveApplication $application): array
    {
        $path = app(LeaveFormDocuments::class)->workbook($application->fresh());
        $zip = new ZipArchive();
        $zip->open($path);

        $parts = [
            'drawing' => (string) $zip->getFromName('xl/drawings/drawing1.xml'),
            'vml' => (string) $zip->getFromName('xl/drawings/vmlDrawing1.vml'),
            'sheet' => (string) $zip->getFromName('xl/worksheets/sheet1.xml'),
        ];
        $zip->close();

        return $parts;
    }

    /** The shape ids of the ticked check boxes. */
    private function ticked(string $vml): array
    {
        preg_match_all('/<v:shape\b.*?<\/v:shape>/s', $vml, $shapes);

        return collect($shapes[0])
            ->filter(fn ($s) => str_contains($s, '<x:Checked>1</x:Checked>'))
            ->map(fn ($s) => preg_match('/_x0000_s(\d+)/', $s, $m) ? (int) $m[1] : null)
            ->values()->all();
    }

    // ------------------------------------------------------------------
    // The types of leave
    // ------------------------------------------------------------------

    public function test_the_type_list_is_the_forms_own(): void
    {
        $this->assertSame(LeaveTypes::labels(), LeaveApplication::TYPES);

        // Every 6.A type has a tick box on the form.
        foreach (LeaveTypes::inGroup(LeaveTypes::FORM) as $code => $type) {
            $this->assertArrayHasKey($code, CsForm6::TYPE_BOXES, $type['label']);
        }
    }

    // ------------------------------------------------------------------
    // Filing
    // ------------------------------------------------------------------

    public function test_the_form_opens_with_items_one_to_four_filled_in(): void
    {
        $this->actingAs($this->employee)
            ->get(route('leave.apply'))
            ->assertOk()
            ->assertSee('College of Arts and Sciences')
            ->assertSee('DELA CRUZ, JUAN SANTOS')
            ->assertSee('Instructor I')
            ->assertSee('Mandatory/Forced Leave')
            ->assertSee('Special Emergency (Calamity) Leave')
            ->assertSee('Monetization of Leave Credits');
    }

    public function test_a_filing_is_saved_and_sent_to_the_dean(): void
    {
        $application = $this->file($this->vacation());

        $this->assertTrue($application->isOnline());
        $this->assertSame('submitted', $application->status);
        $this->assertSame('VL', $application->leave_type);
        // Monday the 19th to Wednesday the 21st.
        $this->assertEquals(3, (float) $application->days);
        $this->assertSame('Baguio City', $application->form_data['details']['location_specify']);
        $this->assertSame('DELA CRUZ', $application->form_data['applicant']['last_name']);
        $this->assertSame('SANTOS', $application->form_data['applicant']['middle_name']);
        $this->assertNull($application->file_path);
    }

    public function test_details_that_belong_to_another_type_are_dropped(): void
    {
        $application = $this->file([
            'leave_type' => 'SL',
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-02',
            // Left over from choosing vacation leave first.
            'details' => ['location' => 'abroad', 'location_specify' => 'Japan', 'sickness' => 'outpatient', 'illness' => 'Flu'],
            'commutation' => 'not_requested',
        ]);

        $this->assertSame(['sickness' => 'outpatient', 'illness' => 'Flu'], $application->form_data['details']);
    }

    // ------------------------------------------------------------------
    // Page 2
    // ------------------------------------------------------------------

    public function test_the_form_cannot_be_read_without_its_details(): void
    {
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $this->vacation(['details' => ['location' => null, 'location_specify' => null]]))
            ->assertSessionHasErrors('details.location');

        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $this->vacation(['details' => ['location' => 'abroad', 'location_specify' => '']]))
            ->assertSessionHasErrors('details.location_specify');

        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), ['leave_type' => 'SL', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'commutation' => 'not_requested'])
            ->assertSessionHasErrors(['details.sickness', 'details.illness']);

        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), ['leave_type' => 'OTHERS', 'date_from' => '2026-10-19', 'date_to' => '2026-10-19', 'commutation' => 'not_requested'])
            ->assertSessionHasErrors('others_specify');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_the_limits_on_page_2_are_enforced(): void
    {
        // Special privilege leave: three days.
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $this->vacation(['leave_type' => 'SPL', 'date_from' => '2026-10-19', 'date_to' => '2026-10-22']))
            ->assertSessionHasErrors('date_to');

        // Paternity leave: seven days.
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), ['leave_type' => 'PL', 'date_from' => '2026-10-19', 'date_to' => '2026-10-28', 'commutation' => 'not_requested'])
            ->assertSessionHasErrors('date_to');

        // Special leave benefits for women: two months.
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), ['leave_type' => 'SLBW', 'date_from' => '2026-10-19', 'date_to' => '2026-12-25',
                'details' => ['women_illness' => 'Myomectomy'], 'commutation' => 'not_requested'])
            ->assertSessionHasErrors('date_to');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_the_yearly_allowances_count_earlier_filings(): void
    {
        $this->file($this->vacation(['leave_type' => 'SPL', 'date_from' => '2026-10-19', 'date_to' => '2026-10-20']));

        // Two of the three days are used; two more is one too many.
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $this->vacation(['leave_type' => 'SPL', 'date_from' => '2026-11-09', 'date_to' => '2026-11-10']))
            ->assertSessionHasErrors('date_to');

        // One more fits.
        $this->file($this->vacation(['leave_type' => 'SPL', 'date_from' => '2026-11-09', 'date_to' => '2026-11-09']));
    }

    public function test_calamity_leave_is_within_thirty_days_and_once_a_year(): void
    {
        $calamity = ['leave_type' => 'SEL', 'commutation' => 'not_requested', 'details' => ['calamity_date' => '2026-09-01']];

        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $calamity + ['date_from' => '2026-10-06', 'date_to' => '2026-10-07'])
            ->assertSessionHasErrors('date_to');

        $calamity['details']['calamity_date'] = '2026-10-01';
        $this->file($calamity + ['date_from' => '2026-10-06', 'date_to' => '2026-10-07']);

        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $calamity + ['date_from' => '2026-10-12', 'date_to' => '2026-10-12'])
            ->assertSessionHasErrors('leave_type');
    }

    public function test_softer_rules_warn_but_let_the_filing_through(): void
    {
        // Vacation leave starting in two days — "five days in advance, whenever possible".
        $this->actingAs($this->employee)
            ->post(route('leave.apply.store'), $this->vacation(['date_from' => '2026-10-07', 'date_to' => '2026-10-07']))
            ->assertRedirect(route('leave.index'))
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'five (5) days ahead'));

        $this->assertSame(1, LeaveApplication::count());
    }

    public function test_the_live_check_answers_as_the_submission_would(): void
    {
        $this->actingAs($this->employee)
            ->postJson(route('leave.apply.check'), [
                'leave_type' => 'SL',
                'date_from' => '2026-10-12',
                'date_to' => '2026-10-19',
                'details' => ['sickness' => 'hospital', 'illness' => 'Dengue'],
                'commutation' => 'not_requested',
            ])
            ->assertOk()
            ->assertJsonPath('days', 6)
            ->assertJsonPath('errors', [])
            // Filed ahead and over five days: a medical certificate, and
            // more days than the four sick leave credits.
            ->assertJson(fn ($json) => $json
                ->where('documents', fn ($docs) => str_contains(collect($docs)->implode(' '), 'medical certificate'))
                ->where('warnings', fn ($w) => str_contains(collect($w)->implode(' '), 'without pay'))
                ->etc());
    }

    public function test_maternity_leave_counts_calendar_days(): void
    {
        $result = app(LeavePolicy::class)->check([
            'leave_type' => 'ML', 'date_from' => '2026-10-05', 'date_to' => '2027-01-17', 'commutation' => 'not_requested',
        ], $this->employee);

        $this->assertSame(105.0, $result['days']);
        $this->assertSame([], $result['errors']);

        $result = app(LeavePolicy::class)->check([
            'leave_type' => 'ML', 'date_from' => '2026-10-05', 'date_to' => '2027-01-18', 'commutation' => 'not_requested',
        ], $this->employee);

        $this->assertArrayHasKey('date_to', $result['errors']);
    }

    // ------------------------------------------------------------------
    // The printed form
    // ------------------------------------------------------------------

    public function test_the_printed_form_carries_the_answers_and_the_ticks(): void
    {
        $application = $this->file($this->vacation());
        $printed = $this->printed($application);

        foreach (['College of Arts and Sciences', 'DELA CRUZ', 'JUAN', 'SANTOS', 'Instructor I', '32,870.00',
                  'Baguio City', '3 working days', 'October 19–21, 2026', 'October 5, 2026'] as $text) {
            $this->assertStringContainsString($text, $printed['drawing'], $text);
        }

        $this->assertEqualsCanonicalizing(
            [CsForm6::TYPE_BOXES['VL'], CsForm6::DETAIL_BOXES['within_philippines'], CsForm6::DETAIL_BOXES['not_requested']],
            $this->ticked($printed['vml']),
        );

        // The Dean is named under 7.B, the applicant over their signature line.
        $this->assertStringContainsString('MARIA L. REYES', $printed['sheet']);
        $this->assertStringContainsString('JUAN S. DELA CRUZ', $printed['sheet']);
    }

    public function test_each_decision_is_printed_as_it_is_made(): void
    {
        $application = $this->file($this->vacation());

        $this->actingAs($this->dean)->post(route('admin.leave.review.approve', $application))->assertRedirect();
        $this->assertContains(CsForm6::DETAIL_BOXES['for_approval'], $this->ticked($this->printed($application)['vml']));

        // HR's approval certifies the credits as they stand.
        $this->actingAs($this->hr)->post(route('admin.leave.review.approve', $application))->assertRedirect();
        $application->refresh();
        $this->assertSame(10.0, (float) $application->credit_certification['vl']);

        // The ledger moving on afterwards does not change what HR certified.
        $application->user->leaveBalance->update(['vl_balance' => 1]);

        $this->actingAs($this->director)->post(route('admin.leave.review.approve', $application))->assertRedirect();

        $sheet = $this->printed($application)['sheet'];
        $this->assertStringContainsString('10.000', $sheet);  // VL total earned
        $this->assertStringContainsString('7.000', $sheet);   // VL balance after three days

        // 7.C: three days with pay.
        $this->assertStringContainsString('<a:t>3</a:t>', $this->printed($application)['drawing']);
    }

    public function test_a_returned_form_prints_the_reason_and_can_be_corrected(): void
    {
        $application = $this->file($this->vacation());

        $this->actingAs($this->dean)
            ->post(route('admin.leave.review.return', $application), ['remarks' => 'Attach your travel authority.'])
            ->assertRedirect();

        $printed = $this->printed($application);
        $this->assertContains(CsForm6::DETAIL_BOXES['for_disapproval'], $this->ticked($printed['vml']));
        // Over the ruled lines under "For disapproval due to", as far as
        // each line holds.
        $this->assertStringContainsString('<a:t>Attach your travel</a:t>', $printed['drawing']);
        $this->assertStringContainsString('<a:t>authority.</a:t>', $printed['drawing']);

        // The upload route is not how an on-screen form is corrected.
        $this->actingAs($this->employee)
            ->post(route('leave.resubmit', $application), ['leave_form' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertStatus(422);

        $this->actingAs($this->employee)->get(route('leave.edit', $application))
            ->assertOk()
            ->assertSee('Attach your travel authority.');

        $this->actingAs($this->employee)
            ->post(route('leave.update', $application), $this->vacation([
                'details' => ['location' => 'abroad', 'location_specify' => 'Japan'],
                'attachments' => [UploadedFile::fake()->create('travel-authority.pdf', 50, 'application/pdf')],
            ]))
            ->assertRedirect(route('leave.index'))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('pending', $application->dean_status);
        $this->assertSame('Japan', $application->form_data['details']['location_specify']);
        $this->assertCount(1, $application->attachments());

        // The Dean's earlier recommendation is gone from the form.
        $this->assertNotContains(CsForm6::DETAIL_BOXES['for_disapproval'], $this->ticked($this->printed($application)['vml']));
    }

    public function test_the_form_is_read_as_a_pdf_by_the_employee_and_the_reviewers_only(): void
    {
        $application = $this->file($this->vacation());

        $this->actingAs($this->employee)->get(route('leave.form.pdf', $application))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->dean)->get(route('admin.leave.review.form.pdf', $application))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->dean)->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->assertSee('Leave form · CS Form No. 6')
            ->assertSee('Within the Philippines: Baguio City');

        $stranger = $this->person('employee');
        $this->actingAs($stranger)->get(route('leave.form.pdf', $application))->assertForbidden();
        $this->actingAs($stranger)->get(route('leave.form.download', $application))->assertForbidden();
    }

    public function test_supporting_documents_are_private_to_the_chain(): void
    {
        $application = $this->file([
            'leave_type' => 'SL', 'date_from' => '2026-09-28', 'date_to' => '2026-10-02', 'commutation' => 'not_requested',
            'details' => ['sickness' => 'hospital', 'illness' => 'Dengue'],
            'attachments' => [UploadedFile::fake()->create('medical-certificate.pdf', 80, 'application/pdf')],
        ]);

        $this->assertCount(1, $application->attachments());

        $this->actingAs($this->employee)->get(route('leave.attachment', [$application, 0]))->assertOk();
        $this->actingAs($this->dean)->get(route('admin.leave.review.attachment', [$application, 0]))->assertOk();
        $this->actingAs($this->person('employee'))->get(route('leave.attachment', [$application, 0]))->assertForbidden();
    }

    public function test_the_completed_form_is_what_prints_once_approved(): void
    {
        $application = $this->file($this->vacation());

        $this->actingAs($this->employee)->get(route('leave.print', $application))->assertForbidden();

        foreach ([$this->dean, $this->hr, $this->director] as $reviewer) {
            $this->actingAs($reviewer)->post(route('admin.leave.review.approve', $application));
        }

        $this->actingAs($this->employee)->get(route('leave.print', $application))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->employee)->get(route('leave.index'))
            ->assertSee('Print leave form');
    }

    public function test_days_only_leave_stays_off_the_calendar(): void
    {
        $application = $this->file(['leave_type' => 'MONETIZE', 'days' => 5, 'commutation' => 'requested']);

        $this->assertEquals(5, (float) $application->days);
        $this->assertTrue($application->isDaysOnly());
        $this->assertContains(CsForm6::DETAIL_BOXES['monetization'], $this->ticked($this->printed($application)['vml']));

        // Monetizing does not block real leave on the filing date.
        $this->file($this->vacation(['date_from' => '2026-10-05', 'date_to' => '2026-10-05']));
    }

    // ------------------------------------------------------------------
    // The template
    // ------------------------------------------------------------------

    public function test_the_campus_forms_printing_faults_are_corrected_once(): void
    {
        $original = base_path('tests/Fixtures/cs-form-6-original.xlsx');
        $copy = tempnam(sys_get_temp_dir(), 'cs6') . '.xlsx';
        copy($original, $copy);

        $repairs = app(CsForm6Repairs::class);

        $this->assertCount(4, $repairs->repair($copy));
        $this->assertSame([], $repairs->repair($copy), 'a second pass changes nothing');
        $this->assertSame([], $repairs->repair(resource_path('templates/CS-Form-6-2020.xlsx')), 'the bundled form is already corrected');

        $zip = new ZipArchive();
        $zip->open($copy);
        $this->assertFalse($zip->getFromName('xl/media/image3.emf'), 'the clipped instructions picture is gone');
        $this->assertNotFalse($zip->getFromName('xl/media/cs-form-6-instructions.png'));
        $this->assertStringContainsString('$A$1:$L$64', $zip->getFromName('xl/workbook.xml'));
        // Every checkbox and the signatories survive.
        $this->assertSame(25, substr_count((string) $zip->getFromName('xl/drawings/vmlDrawing1.vml'), 'ObjectType="Checkbox"'));
        $this->assertStringContainsString('GACUTAN', (string) $zip->getFromName('xl/drawings/drawing1.xml'));
        $zip->close();

        $filler = new TemplateFiller($copy, $copy . '.probe.xlsx');
        $this->assertTrue(CsForm6::matches($filler));
        $filler->save();

        @unlink($copy);
        @unlink($copy . '.probe.xlsx');
    }

    public function test_a_workbook_that_is_not_cs_form_6_is_left_alone(): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'pds') . '.xlsx';
        copy(resource_path('templates/CS-Form-212-2026.xlsx'), $copy);
        $before = hash_file('sha256', $copy);

        $this->assertSame([], app(CsForm6Repairs::class)->repair($copy));
        $this->assertSame($before, hash_file('sha256', $copy));

        @unlink($copy);
    }
}
