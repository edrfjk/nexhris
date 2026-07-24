<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 25px 30px; }
    body { font-family: Arial, sans-serif; font-size: 8px; color: #000; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
    .no-border td, .no-border th { border: none; }
    .header-title { text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 2px; }
    .header-warning { font-size: 7px; text-align: justify; margin-bottom: 4px; }
    .section-title { background: #d9d9d9; font-weight: bold; padding: 3px 5px; font-size: 9px; }
    .label { font-weight: bold; font-size: 7px; }
    .value { font-size: 8px; }
    .page-break { page-break-before: always; }
    .footer { text-align: center; font-size: 7px; margin-top: 6px; }
    .checkbox { font-family: 'DejaVu Sans', sans-serif; }
</style>
</head>
<body>

{{-- ============ PAGE 1 ============ --}}
<div class="header-title">PERSONAL DATA SHEET</div>
<div class="header-warning">
    WARNING: Any misrepresentation made in the Personal Data Sheet shall cause the filing of administrative/criminal case/s against the person concerned.<br>
    Print legibly. Tick appropriate boxes. Indicate N/A if not applicable. DO NOT ABBREVIATE.
</div>

@php $p = $user->pdsPersonalInformation; @endphp

<table class="section-title"><tr><td>I. PERSONAL INFORMATION</td></tr></table>
<table>
    <tr>
        <td class="label" width="15%">1. SURNAME</td>
        <td class="value" width="35%">{{ $p->surname ?? '' }}</td>
        <td class="label" width="15%">16. CITIZENSHIP</td>
        <td class="value" width="35%">{{ $p->citizenship ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">2. FIRST NAME</td>
        <td class="value">{{ $p->first_name ?? '' }} {{ $p->name_extension ?? '' }}</td>
        <td class="label">Dual Citizen?</td>
        <td class="value">{{ ($p->is_dual_citizen ?? false) ? 'Yes - ' . $p->dual_citizenship_country : 'No' }}</td>
    </tr>
    <tr>
        <td class="label">MIDDLE NAME</td>
        <td class="value">{{ $p->middle_name ?? '' }}</td>
        <td class="label">17. RESIDENTIAL ADDRESS</td>
        <td class="value">
            {{ $p->res_house_block_lot ?? '' }} {{ $p->res_street ?? '' }}, {{ $p->res_subdivision_village ?? '' }},
            {{ $p->res_barangay ?? '' }}, {{ $p->res_city_municipality ?? '' }}, {{ $p->res_province ?? '' }} {{ $p->res_zip_code ?? '' }}
        </td>
    </tr>
    <tr>
        <td class="label">3. DATE OF BIRTH</td>
        <td class="value">{{ optional($p->date_of_birth)->format('m/d/Y') }}</td>
        <td class="label">18. PERMANENT ADDRESS</td>
        <td class="value">
            {{ $p->perm_house_block_lot ?? '' }} {{ $p->perm_street ?? '' }}, {{ $p->perm_subdivision_village ?? '' }},
            {{ $p->perm_barangay ?? '' }}, {{ $p->perm_city_municipality ?? '' }}, {{ $p->perm_province ?? '' }} {{ $p->perm_zip_code ?? '' }}
        </td>
    </tr>
    <tr>
        <td class="label">4. PLACE OF BIRTH</td>
        <td class="value">{{ $p->place_of_birth ?? '' }}</td>
        <td class="label">19. TELEPHONE NO.</td>
        <td class="value">{{ $p->telephone_no ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">5. SEX</td>
        <td class="value">{{ $p->sex ?? '' }}</td>
        <td class="label">20. MOBILE NO.</td>
        <td class="value">{{ $p->mobile_no ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">6. CIVIL STATUS</td>
        <td class="value">{{ $p->civil_status ?? '' }} {{ $p->civil_status === 'Others' ? '- ' . $p->civil_status_others : '' }}</td>
        <td class="label">21. E-MAIL ADDRESS</td>
        <td class="value">{{ $p->email_address ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">7. HEIGHT (m)</td><td class="value">{{ $p->height_m ?? '' }}</td>
        <td class="label">8. WEIGHT (kg)</td><td class="value">{{ $p->weight_kg ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">9. BLOOD TYPE</td><td class="value">{{ $p->blood_type ?? '' }}</td>
        <td class="label">10. GSIS/UMID NO.</td><td class="value">{{ $p->gsis_umid_no ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">11. PAG-IBIG NO.</td><td class="value">{{ $p->pagibig_no ?? '' }}</td>
        <td class="label">12. PHILHEALTH NO.</td><td class="value">{{ $p->philhealth_no ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">13. PhilSys (PSN)</td><td class="value">{{ $p->psn_no ?? '' }}</td>
        <td class="label">14. TIN NO.</td><td class="value">{{ $p->tin_no ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">15. AGENCY EMPLOYEE NO.</td>
        <td class="value" colspan="3">{{ $p->agency_employee_no ?? '' }}</td>
    </tr>
</table>

@php $fam = $user->pdsFamilyBackground; @endphp
<table class="section-title" style="margin-top:4px;"><tr><td>II. FAMILY BACKGROUND</td></tr></table>
<table>
    <tr>
        <td class="label" width="15%">22. SPOUSE'S SURNAME</td>
        <td class="value" width="35%">{{ $fam->spouse_surname ?? 'N/A' }}</td>
        <td class="label" width="15%">23. CHILDREN (Name / Date of Birth)</td>
        <td class="value" width="35%" rowspan="4">
            @forelse ($user->pdsChildren as $child)
                {{ $child->full_name }} — {{ $child->date_of_birth->format('m/d/Y') }}<br>
            @empty
                N/A
            @endforelse
        </td>
    </tr>
    <tr>
        <td class="label">FIRST NAME</td>
        <td class="value">{{ $fam->spouse_first_name ?? '' }} {{ $fam->spouse_name_extension ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">MIDDLE NAME</td>
        <td class="value">{{ $fam->spouse_middle_name ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">OCCUPATION</td>
        <td class="value">{{ $fam->spouse_occupation ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">24. FATHER'S NAME</td>
        <td class="value" colspan="3">
            {{ $fam->father_surname ?? '' }}, {{ $fam->father_first_name ?? '' }} {{ $fam->father_middle_name ?? '' }} {{ $fam->father_name_extension ?? '' }}
        </td>
    </tr>
    <tr>
        <td class="label">25. MOTHER'S MAIDEN NAME</td>
        <td class="value" colspan="3">
            {{ $fam->mother_maiden_surname ?? '' }}, {{ $fam->mother_first_name ?? '' }} {{ $fam->mother_middle_name ?? '' }}
        </td>
    </tr>
</table>

<table class="section-title" style="margin-top:4px;"><tr><td>III. EDUCATIONAL BACKGROUND</td></tr></table>
<table>
    <tr class="label">
        <td width="15%">LEVEL</td><td width="25%">NAME OF SCHOOL</td><td width="20%">DEGREE/COURSE</td>
        <td width="14%">PERIOD (From-To)</td><td width="12%">YEAR GRADUATED</td><td width="14%">HONORS</td>
    </tr>
    @foreach (['Elementary','Secondary','Vocational/Trade Course','College','Graduate Studies'] as $level)
        @php $rows = $user->pdsEducationalBackgrounds->where('level', $level); @endphp
        @forelse ($rows as $edu)
            <tr>
                <td class="value">{{ $level }}</td>
                <td class="value">{{ $edu->school_name }}</td>
                <td class="value">{{ $edu->degree_course }}</td>
                <td class="value">{{ optional($edu->period_from)->format('Y') }}-{{ optional($edu->period_to)->format('Y') }}</td>
                <td class="value">{{ $edu->year_graduated }}</td>
                <td class="value">{{ $edu->scholarship_honors }}</td>
            </tr>
        @empty
            <tr><td class="value">{{ $level }}</td><td colspan="5" class="value"></td></tr>
        @endforelse
    @endforeach
</table>

<div class="footer">CS FORM 212 (Revised 2026), Page 1 of 4 &nbsp;|&nbsp; Signature: _____________________ &nbsp; Date: _____________</div>

{{-- ============ PAGE 2 ============ --}}
<div class="page-break"></div>

<table class="section-title"><tr><td>IV. CIVIL SERVICE ELIGIBILITY</td></tr></table>
<table>
    <tr class="label">
        <td width="30%">ELIGIBILITY</td><td width="12%">RATING</td><td width="15%">DATE OF EXAM</td>
        <td width="20%">PLACE OF EXAM</td><td width="23%">LICENSE NO. / VALID UNTIL</td>
    </tr>
    @forelse ($user->pdsCivilServiceEligibilities as $elig)
        <tr>
            <td class="value">{{ $elig->eligibility_name }}</td>
            <td class="value">{{ $elig->rating }}</td>
            <td class="value">{{ optional($elig->exam_date)->format('m/d/Y') }}</td>
            <td class="value">{{ $elig->exam_place }}</td>
            <td class="value">{{ $elig->license_number }} {{ $elig->license_valid_until ? '/ ' . $elig->license_valid_until->format('m/d/Y') : '' }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="value">N/A</td></tr>
    @endforelse
</table>

<table class="section-title" style="margin-top:4px;"><tr><td>V. WORK EXPERIENCE</td></tr></table>
<table>
    <tr class="label">
        <td width="16%">INCLUSIVE DATES</td><td width="22%">POSITION TITLE</td><td width="26%">DEPARTMENT/AGENCY/COMPANY</td>
        <td width="12%">MONTHLY SALARY</td><td width="12%">STATUS</td><td width="12%">GOV'T SERVICE</td>
    </tr>
    @forelse ($user->pdsWorkExperiences as $work)
        <tr>
            <td class="value">{{ $work->date_from->format('m/d/Y') }} - {{ $work->date_to ? $work->date_to->format('m/d/Y') : 'Present' }}</td>
            <td class="value">{{ $work->position_title }}</td>
            <td class="value">{{ $work->department_agency_office_company }}</td>
            <td class="value">{{ $work->monthly_salary ? number_format($work->monthly_salary, 2) : '' }}</td>
            <td class="value">{{ $work->status_of_appointment }}</td>
            <td class="value">{{ $work->is_government_service ? 'Y' : 'N' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="value">N/A</td></tr>
    @endforelse
</table>

<div class="footer">CS FORM 212 (Revised 2026), Page 2 of 4 &nbsp;|&nbsp; Signature: _____________________ &nbsp; Date: _____________</div>

{{-- ============ PAGE 3 ============ --}}
<div class="page-break"></div>

<table class="section-title"><tr><td>VI. VOLUNTARY WORK / INVOLVEMENT</td></tr></table>
<table>
    <tr class="label">
        <td width="40%">ORGANIZATION NAME & ADDRESS</td><td width="20%">INCLUSIVE DATES</td>
        <td width="15%">NO. OF HOURS</td><td width="25%">POSITION / NATURE OF WORK</td>
    </tr>
    @forelse ($user->pdsVoluntaryWorks as $vw)
        <tr>
            <td class="value">{{ $vw->organization_name_address }}</td>
            <td class="value">{{ $vw->date_from->format('m/d/Y') }} - {{ $vw->date_to ? $vw->date_to->format('m/d/Y') : 'Present' }}</td>
            <td class="value">{{ $vw->number_of_hours }}</td>
            <td class="value">{{ $vw->position_nature_of_work }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="value">N/A</td></tr>
    @endforelse
</table>

<table class="section-title" style="margin-top:4px;"><tr><td>VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS</td></tr></table>
<table>
    <tr class="label">
        <td width="35%">TITLE</td><td width="18%">INCLUSIVE DATES</td>
        <td width="12%">HOURS</td><td width="15%">TYPE</td><td width="20%">CONDUCTED/SPONSORED BY</td>
    </tr>
    @forelse ($user->pdsTrainings as $t)
        <tr>
            <td class="value">{{ $t->title }}</td>
            <td class="value">{{ $t->date_from->format('m/d/Y') }} - {{ $t->date_to ? $t->date_to->format('m/d/Y') : '' }}</td>
            <td class="value">{{ $t->number_of_hours }}</td>
            <td class="value">{{ $t->type }}</td>
            <td class="value">{{ $t->conducted_sponsored_by }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="value">N/A</td></tr>
    @endforelse
</table>

@php $other = $user->pdsOtherInformation; @endphp
<table class="section-title" style="margin-top:4px;"><tr><td>VIII. OTHER INFORMATION</td></tr></table>
<table>
    <tr>
        <td class="label" width="33%">31. SPECIAL SKILLS AND HOBBIES</td>
        <td class="label" width="33%">32. NON-ACADEMIC DISTINCTIONS</td>
        <td class="label" width="34%">33. MEMBERSHIP IN ASSOCIATIONS</td>
    </tr>
    <tr>
        <td class="value">{!! implode('<br>', $other->special_skills_hobbies ?? ['N/A']) !!}</td>
        <td class="value">{!! implode('<br>', $other->non_academic_distinctions ?? ['N/A']) !!}</td>
        <td class="value">{!! implode('<br>', $other->membership_associations ?? ['N/A']) !!}</td>
    </tr>
</table>

<div class="footer">CS FORM 212 (Revised 2026), Page 3 of 4 &nbsp;|&nbsp; Signature: _____________________ &nbsp; Date: _____________</div>

{{-- ============ PAGE 4 ============ --}}
<div class="page-break"></div>

@php $q = $user->pdsQuestionnaire; $d = $user->pdsDeclaration; @endphp

<table>
    <tr>
        <td class="label" width="5%">34.</td>
        <td width="70%">
            Are you related by consanguinity or affinity to the appointing/recommending authority, or to the chief of bureau/office, or to the person with immediate supervision over you?<br>
            a. within the third degree? <b>{{ ($q->related_third_degree ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->related_third_degree_details ?? '' }}<br>
            b. within the fourth degree (LGU)? <b>{{ ($q->related_fourth_degree ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->related_fourth_degree_details ?? '' }}
        </td>
    </tr>
    <tr>
        <td class="label">35.</td>
        <td>
            a. Found guilty of any administrative offense? <b>{{ ($q->found_admin_guilty ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->found_admin_guilty_details ?? '' }}<br>
            b. Criminally charged before any court? <b>{{ ($q->criminally_charged ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->criminally_charged_details ?? '' }}
            @if ($q->criminally_charged ?? false)
                (Date Filed: {{ optional($q->criminally_charged_date_filed)->format('m/d/Y') }}, Status: {{ $q->criminally_charged_status }})
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">36.</td>
        <td>Ever convicted of any crime/violation of law? <b>{{ ($q->convicted_crime ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->convicted_crime_details ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">37.</td>
        <td>Ever separated from the service (resignation, retirement, dismissal, etc.)? <b>{{ ($q->separated_from_service ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->separated_from_service_details ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">38.</td>
        <td>
            a. Candidate in a national/local election (except barangay)? <b>{{ ($q->candidate_in_election ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->candidate_in_election_details ?? '' }}<br>
            b. Resigned during the 3-month period before the last election? <b>{{ ($q->resigned_before_election ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->resigned_before_election_details ?? '' }}
        </td>
    </tr>
    <tr>
        <td class="label">39.</td>
        <td>Acquired immigrant/permanent resident status of another country? <b>{{ ($q->acquired_immigrant_status ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->acquired_immigrant_status_country ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">40.</td>
        <td>
            a. Member of an indigenous group? <b>{{ ($q->is_indigenous_group_member ?? false) ? 'YES' : 'NO' }}</b> — {{ $q->indigenous_group_details ?? '' }}<br>
            b. Person with disability? <b>{{ ($q->is_pwd ?? false) ? 'YES' : 'NO' }}</b> — PWD ID: {{ $q->pwd_id_no ?? '' }}<br>
            c. Solo parent? <b>{{ ($q->is_solo_parent ?? false) ? 'YES' : 'NO' }}</b> — Solo Parent ID: {{ $q->solo_parent_id_no ?? '' }}
        </td>
    </tr>
</table>

<table class="section-title" style="margin-top:4px;"><tr><td>41. REFERENCES</td></tr></table>
<table>
    <tr class="label"><td width="34%">NAME</td><td width="33%">ADDRESS</td><td width="33%">CONTACT NO./EMAIL</td></tr>
    @forelse ($user->pdsReferences as $ref)
        <tr>
            <td class="value">{{ $ref->name }}</td>
            <td class="value">{{ $ref->address }}</td>
            <td class="value">{{ $ref->contact_no_email }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="value">N/A</td></tr>
    @endforelse
</table>

<table style="margin-top:6px;">
    <tr>
        <td width="65%">
            42. I declare under oath that I have personally accomplished this Personal Data Sheet which is a true, correct
            and complete statement pursuant to the provisions of pertinent laws, rules and regulations of the Republic of the Philippines.
            <br><br>
            Government ID: {{ $d->government_id_type ?? '' }}<br>
            ID/License/Passport No.: {{ $d->government_id_no ?? '' }}<br>
            Date/Place of Issuance: {{ $d->id_issuance_date_place ?? '' }}
            <br><br><br>
            _______________________________<br>
            Signature over Printed Name<br>
            Date Accomplished: {{ optional($d->date_accomplished)->format('m/d/Y') }}
        </td>
        <td width="35%" style="text-align:center;">
            @if ($d && $d->photo_path)
                <img src="{{ storage_path('app/public/' . $d->photo_path) }}" style="width:80px;height:80px;object-fit:cover;border:1px solid #000;">
            @else
                <div style="width:80px;height:80px;border:1px solid #000;display:inline-block;">&nbsp;</div>
            @endif
            <div style="font-size:6px;">PHOTO</div>
            <br>
            @if ($d && $d->signature_path)
                <img src="{{ storage_path('app/public/' . $d->signature_path) }}" style="width:120px;height:40px;object-fit:contain;border:1px solid #000;">
            @else
                <div style="width:120px;height:40px;border:1px solid #000;display:inline-block;">&nbsp;</div>
            @endif
            <div style="font-size:6px;">SIGNATURE</div>
        </td>
    </tr>
</table>

<div class="footer">CS FORM 212 (Revised 2026), Page 4 of 4</div>

</body>
</html>