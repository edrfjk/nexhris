@php $q = $user->pdsQuestionnaire; @endphp

<form method="POST" action="{{ route('pds.questionnaire.update') }}" class="space-y-6">
    @csrf @method('PUT')
    <input type="hidden" name="next_step" value="{{ $step + 1 }}">

    <div>
        <p class="text-sm font-medium mb-2">34. Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau/office, or to the person with immediate supervision over you?</p>
        <div class="pl-4 space-y-3">
            @include('employee.pds.partials.yesno', ['field' => 'related_third_degree', 'label' => 'a. Within the third degree?', 'value' => $q->related_third_degree ?? false, 'details' => $q->related_third_degree_details ?? ''])
            @include('employee.pds.partials.yesno', ['field' => 'related_fourth_degree', 'label' => 'b. Within the fourth degree (for LGU career employees)?', 'value' => $q->related_fourth_degree ?? false, 'details' => $q->related_fourth_degree_details ?? ''])
        </div>
    </div>

    <div>
        <p class="text-sm font-medium mb-2">35. Administrative and Criminal Record</p>
        <div class="pl-4 space-y-3">
            @include('employee.pds.partials.yesno', ['field' => 'found_admin_guilty', 'label' => 'a. Have you ever been found guilty of any administrative offense?', 'value' => $q->found_admin_guilty ?? false, 'details' => $q->found_admin_guilty_details ?? ''])
            @include('employee.pds.partials.yesno', ['field' => 'criminally_charged', 'label' => 'b. Have you been criminally charged before any court?', 'value' => $q->criminally_charged ?? false, 'details' => $q->criminally_charged_details ?? ''])

            <div class="grid grid-cols-2 gap-3 pl-4">
                <div>
                    <label class="block text-xs mb-1">Date Filed (if applicable)</label>
                    <input type="date" name="criminally_charged_date_filed"
                           value="{{ old('criminally_charged_date_filed', optional($q->criminally_charged_date_filed ?? null)->format('Y-m-d')) }}"
                           class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs mb-1">Status of Case</label>
                    <input name="criminally_charged_status" value="{{ old('criminally_charged_status', $q->criminally_charged_status ?? '') }}"
                           class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
            </div>
        </div>
    </div>

    <div class="pl-4">
        @include('employee.pds.partials.yesno', ['field' => 'convicted_crime', 'label' => '36. Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?', 'value' => $q->convicted_crime ?? false, 'details' => $q->convicted_crime_details ?? ''])
    </div>

    <div class="pl-4">
        @include('employee.pds.partials.yesno', ['field' => 'separated_from_service', 'label' => '37. Have you ever been separated from the service via resignation, retirement, dismissal, dropped from rolls, or any other mode?', 'value' => $q->separated_from_service ?? false, 'details' => $q->separated_from_service_details ?? ''])
    </div>

    <div>
        <p class="text-sm font-medium mb-2">38. Election-Related</p>
        <div class="pl-4 space-y-3">
            @include('employee.pds.partials.yesno', ['field' => 'candidate_in_election', 'label' => 'a. Have you ever been a candidate in a national or local election (except barangay election)?', 'value' => $q->candidate_in_election ?? false, 'details' => $q->candidate_in_election_details ?? ''])
            @include('employee.pds.partials.yesno', ['field' => 'resigned_before_election', 'label' => 'b. Have you resigned from government service during the 3-month period before the last election?', 'value' => $q->resigned_before_election ?? false, 'details' => $q->resigned_before_election_details ?? ''])
        </div>
    </div>

    <div class="pl-4">
        <p class="text-sm font-medium mb-2">39. Have you acquired the status of an immigrant or permanent resident of another country?</p>
        <label class="inline-flex items-center gap-1 text-sm mr-4"><input type="radio" name="acquired_immigrant_status" value="1" @checked(old('acquired_immigrant_status', $q->acquired_immigrant_status ?? false))> Yes</label>
        <label class="inline-flex items-center gap-1 text-sm"><input type="radio" name="acquired_immigrant_status" value="0" @checked(!old('acquired_immigrant_status', $q->acquired_immigrant_status ?? false))> No</label>
        <input name="acquired_immigrant_status_country" placeholder="If yes, indicate country"
               value="{{ old('acquired_immigrant_status_country', $q->acquired_immigrant_status_country ?? '') }}"
               class="mt-2 w-full border rounded px-2 py-1.5 text-sm">
    </div>

    <div>
        <p class="text-sm font-medium mb-2">40. Pursuant to IPRA, Magna Carta for Disabled Persons, and Solo Parents' Welfare Act</p>
        <div class="pl-4 space-y-3">
            @include('employee.pds.partials.yesno', ['field' => 'is_indigenous_group_member', 'label' => 'a. Are you a member of any indigenous group?', 'value' => $q->is_indigenous_group_member ?? false, 'details' => $q->indigenous_group_details ?? '', 'detailsLabel' => 'If yes, please specify'])
            @include('employee.pds.partials.yesno', ['field' => 'is_pwd', 'label' => 'b. Are you a person with disability?', 'value' => $q->is_pwd ?? false, 'details' => $q->pwd_id_no ?? '', 'detailsLabel' => 'If yes, PWD ID No.'])
            @include('employee.pds.partials.yesno', ['field' => 'is_solo_parent', 'label' => 'c. Are you a solo parent?', 'value' => $q->is_solo_parent ?? false, 'details' => $q->solo_parent_id_no ?? '', 'detailsLabel' => 'If yes, Solo Parent ID No.'])
        </div>
    </div>

    <button class="bg-maroon-800 text-white px-6 py-2 rounded text-sm hover:bg-maroon-900">Save & Continue →</button>
</form>