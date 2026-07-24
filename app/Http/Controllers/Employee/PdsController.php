<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PdsController extends Controller
{
    public function edit()
    {
        $user = Auth::user()->load([
            'pdsPersonalInformation', 'pdsFamilyBackground', 'pdsChildren',
            'pdsEducationalBackgrounds', 'pdsCivilServiceEligibilities',
            'pdsWorkExperiences', 'pdsVoluntaryWorks', 'pdsTrainings',
            'pdsOtherInformation', 'pdsQuestionnaire', 'pdsReferences', 'pdsDeclaration',
        ]);

        $currentYear = now()->year;
        $submission = PdsSubmission::firstOrCreate(
            ['user_id' => $user->id, 'applicable_year' => $currentYear],
            ['status' => 'not_started']
        );

        return view('employee.pds.edit', compact('user', 'submission'));
    }

    public function updatePersonal(Request $request)
    {
        $data = $request->validate([
            'surname' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date'],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'civil_status' => ['required', 'in:Single,Married,Widowed,Separated,Solo Parent,Others'],
            'civil_status_others' => ['nullable', 'string', 'max:100'],
            'height_m' => ['nullable', 'numeric'],
            'weight_kg' => ['nullable', 'numeric'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'citizenship' => ['required', 'string', 'max:100'],
            'is_dual_citizen' => ['nullable', 'boolean'],
            'dual_citizenship_country' => ['nullable', 'string', 'max:100'],
            'gsis_umid_no' => ['nullable', 'string', 'max:50'],
            'pagibig_no' => ['nullable', 'string', 'max:50'],
            'philhealth_no' => ['nullable', 'string', 'max:50'],
            'sss_no' => ['nullable', 'string', 'max:50'],
            'psn_no' => ['nullable', 'string', 'max:50'],
            'tin_no' => ['nullable', 'string', 'max:50'],
            'agency_employee_no' => ['nullable', 'string', 'max:50'],
            'res_house_block_lot' => ['nullable', 'string', 'max:255'],
            'res_street' => ['nullable', 'string', 'max:255'],
            'res_subdivision_village' => ['nullable', 'string', 'max:255'],
            'res_barangay' => ['nullable', 'string', 'max:255'],
            'res_city_municipality' => ['nullable', 'string', 'max:255'],
            'res_province' => ['nullable', 'string', 'max:255'],
            'res_zip_code' => ['nullable', 'string', 'max:10'],
            'perm_house_block_lot' => ['nullable', 'string', 'max:255'],
            'perm_street' => ['nullable', 'string', 'max:255'],
            'perm_subdivision_village' => ['nullable', 'string', 'max:255'],
            'perm_barangay' => ['nullable', 'string', 'max:255'],
            'perm_city_municipality' => ['nullable', 'string', 'max:255'],
            'perm_province' => ['nullable', 'string', 'max:255'],
            'perm_zip_code' => ['nullable', 'string', 'max:10'],
            'telephone_no' => ['nullable', 'string', 'max:20'],
            'mobile_no' => ['nullable', 'string', 'max:20'],
            'email_address' => ['nullable', 'email', 'max:255'],
        ]);

        $data['is_dual_citizen'] = $request->boolean('is_dual_citizen');

        Auth::user()->pdsPersonalInformation()->updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        $this->touchSubmission();

        return $this->redirectAfterSave(
    $request,
    'Personal information saved successfully.'
);
    }

    public function updateFamily(Request $request)
    {
        $data = $request->validate([
            'spouse_surname' => ['nullable', 'string', 'max:100'],
            'spouse_first_name' => ['nullable', 'string', 'max:100'],
            'spouse_middle_name' => ['nullable', 'string', 'max:100'],
            'spouse_name_extension' => ['nullable', 'string', 'max:20'],
            'spouse_occupation' => ['nullable', 'string', 'max:255'],
            'spouse_employer_business_name' => ['nullable', 'string', 'max:255'],
            'spouse_business_address' => ['nullable', 'string', 'max:255'],
            'spouse_telephone_no' => ['nullable', 'string', 'max:20'],
            'father_surname' => ['nullable', 'string', 'max:100'],
            'father_first_name' => ['nullable', 'string', 'max:100'],
            'father_middle_name' => ['nullable', 'string', 'max:100'],
            'father_name_extension' => ['nullable', 'string', 'max:20'],
            'mother_maiden_surname' => ['nullable', 'string', 'max:100'],
            'mother_first_name' => ['nullable', 'string', 'max:100'],
            'mother_middle_name' => ['nullable', 'string', 'max:100'],
        ]);

        Auth::user()->pdsFamilyBackground()->updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        $this->touchSubmission();

        return $this->redirectAfterSave(
    $request,
    'Family background saved successfully.'
);
    }

    public function updateOther(Request $request)
    {
        $data = $request->validate([
            'special_skills_hobbies' => ['nullable', 'string'],
            'non_academic_distinctions' => ['nullable', 'string'],
            'membership_associations' => ['nullable', 'string'],
        ]);

        // Textareas: one item per line -> JSON array
        $toArray = fn ($text) => collect(explode("\n", $text ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        Auth::user()->pdsOtherInformation()->updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'special_skills_hobbies' => $toArray($data['special_skills_hobbies'] ?? ''),
                'non_academic_distinctions' => $toArray($data['non_academic_distinctions'] ?? ''),
                'membership_associations' => $toArray($data['membership_associations'] ?? ''),
            ]
        );

        $this->touchSubmission();

        return $this->redirectAfterSave(
    $request,
    'Other information saved.'
);
    }

    public function updateQuestionnaire(Request $request)
    {
        $data = $request->validate([
            'related_third_degree' => ['nullable', 'boolean'],
            'related_third_degree_details' => ['nullable', 'string', 'max:255'],
            'related_fourth_degree' => ['nullable', 'boolean'],
            'related_fourth_degree_details' => ['nullable', 'string', 'max:255'],
            'found_admin_guilty' => ['nullable', 'boolean'],
            'found_admin_guilty_details' => ['nullable', 'string', 'max:255'],
            'criminally_charged' => ['nullable', 'boolean'],
            'criminally_charged_details' => ['nullable', 'string', 'max:255'],
            'criminally_charged_date_filed' => ['nullable', 'date'],
            'criminally_charged_status' => ['nullable', 'string', 'max:255'],
            'convicted_crime' => ['nullable', 'boolean'],
            'convicted_crime_details' => ['nullable', 'string', 'max:255'],
            'separated_from_service' => ['nullable', 'boolean'],
            'separated_from_service_details' => ['nullable', 'string', 'max:255'],
            'candidate_in_election' => ['nullable', 'boolean'],
            'candidate_in_election_details' => ['nullable', 'string', 'max:255'],
            'resigned_before_election' => ['nullable', 'boolean'],
            'resigned_before_election_details' => ['nullable', 'string', 'max:255'],
            'acquired_immigrant_status' => ['nullable', 'boolean'],
            'acquired_immigrant_status_country' => ['nullable', 'string', 'max:100'],
            'is_indigenous_group_member' => ['nullable', 'boolean'],
            'indigenous_group_details' => ['nullable', 'string', 'max:255'],
            'is_pwd' => ['nullable', 'boolean'],
            'pwd_id_no' => ['nullable', 'string', 'max:100'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'solo_parent_id_no' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ([
            'related_third_degree', 'related_fourth_degree', 'found_admin_guilty',
            'criminally_charged', 'convicted_crime', 'separated_from_service',
            'candidate_in_election', 'resigned_before_election', 'acquired_immigrant_status',
            'is_indigenous_group_member', 'is_pwd', 'is_solo_parent',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        Auth::user()->pdsQuestionnaire()->updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        $this->touchSubmission();

        return $this->redirectAfterSave(
    $request,
    'Questionnaire saved.'
);
    }

    public function updateDeclaration(Request $request)
    {
        $data = $request->validate([
            'government_id_type' => ['nullable', 'string', 'max:255'],
            'government_id_no' => ['nullable', 'string', 'max:100'],
            'id_issuance_date_place' => ['nullable', 'string', 'max:255'],
            'date_accomplished' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'image', 'max:2048'],
        ]);

        $declaration = Auth::user()->pdsDeclaration()->firstOrNew(['user_id' => Auth::id()]);

        if ($request->hasFile('photo')) {
            if ($declaration->photo_path) {
                Storage::disk('public')->delete($declaration->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('pds/photos', 'public');
        }

        if ($request->hasFile('signature')) {
            if ($declaration->signature_path) {
                Storage::disk('public')->delete($declaration->signature_path);
            }
            $data['signature_path'] = $request->file('signature')->store('pds/signatures', 'public');
        }

        unset($data['photo'], $data['signature']);

        $declaration->fill($data);
        $declaration->user_id = Auth::id();
        $declaration->save();

        $this->touchSubmission();

        return $this->redirectAfterSave(
    $request,
    'Declaration saved.'
);
    }

    public function submit()
    {
        $submission = PdsSubmission::firstOrCreate(
            ['user_id' => Auth::id(), 'applicable_year' => now()->year],
            ['status' => 'not_started']
        );

        $submission->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return redirect()->route('pds.edit')->with('success', 'PDS submitted to HR for review.');
    }

    private function touchSubmission(): void
    {
        $submission = PdsSubmission::firstOrCreate(
            ['user_id' => Auth::id(), 'applicable_year' => now()->year],
            ['status' => 'not_started']
        );

        if ($submission->status === 'not_started') {
            $submission->update(['status' => 'draft']);
        }
    }

    private function redirectAfterSave(Request $request, string $message)
{
    if ($request->filled('next_step')) {
        return redirect()->route('pds.step', [
            'step' => (int) $request->input('next_step')
        ])->with('success', $message);
    }

    return back()->with('success', $message);
}
}