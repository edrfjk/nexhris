<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PdsSpreadsheetExportService
{
    private const EDUCATION_ROWS = [
        'Elementary' => 54,
        'Secondary' => 55,
        'Vocational/Trade Course' => 56,
        'College' => 57,
        'Graduate Studies' => 58,
    ];

    public function fill(User $user): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $templatePath = storage_path('app/pds-template/CS-Form-212.xlsx');
        $spreadsheet = IOFactory::load($templatePath);

        $this->fillPersonalPage($spreadsheet->getSheetByName('C1'), $user);
        $this->fillEligibilityAndWork($spreadsheet->getSheetByName('C2'), $user);
        $this->fillVoluntaryTrainingOther($spreadsheet->getSheetByName('C3'), $user);
        $this->fillReferencesAndDeclaration($spreadsheet->getSheetByName('C4'), $user);
        $this->fillQuestionnaire($spreadsheet->getSheetByName('C4'), $user);

        return $spreadsheet;
    }

    private function fillPersonalPage(Worksheet $sheet, User $user): void
    {
        $p = $user->pdsPersonalInformation;
        if ($p) {
            $this->set($sheet, 'D10', $p->surname);
            $this->set($sheet, 'D11', $p->first_name);
            $this->set($sheet, 'L12', $p->name_extension);
            $this->set($sheet, 'D12', $p->middle_name);
            $this->set($sheet, 'D13', optional($p->date_of_birth)->format('d/m/Y'));
            $this->set($sheet, 'J13', $p->citizenship);
            $this->set($sheet, 'D15', $p->place_of_birth);

            $this->set($sheet, 'D22', $p->height_m);
            $this->set($sheet, 'D24', $p->weight_kg);
            $this->set($sheet, 'D25', $p->blood_type);
            $this->set($sheet, 'D27', $p->gsis_umid_no);
            $this->set($sheet, 'D29', $p->pagibig_no);
            $this->set($sheet, 'D31', $p->philhealth_no);
            $this->set($sheet, 'D32', $p->psn_no);
            $this->set($sheet, 'D33', $p->tin_no);
            $this->set($sheet, 'D34', $p->agency_employee_no);

            $this->set($sheet, 'I19', $p->res_house_block_lot);
            $this->set($sheet, 'L19', $p->res_street);
            $this->set($sheet, 'I22', $p->res_subdivision_village);
            $this->set($sheet, 'L22', $p->res_barangay);
            $this->set($sheet, 'I24', trim("{$p->res_city_municipality}, {$p->res_province} {$p->res_zip_code}"));

            $this->set($sheet, 'I27', $p->perm_house_block_lot);
            $this->set($sheet, 'L27', $p->perm_street);
            $this->set($sheet, 'I29', $p->perm_subdivision_village);
            $this->set($sheet, 'L29', $p->perm_barangay);
            $this->set($sheet, 'I31', $p->perm_city_municipality);
            $this->set($sheet, 'L31', trim("{$p->perm_province} {$p->perm_zip_code}"));

            $this->set($sheet, 'I32', $p->telephone_no);
            $this->set($sheet, 'I33', $p->mobile_no);
            $this->set($sheet, 'I34', $p->email_address);
        }

        $fam = $user->pdsFamilyBackground;
        if ($fam) {
            $this->set($sheet, 'D36', $fam->spouse_surname);
            $this->set($sheet, 'D37', trim(($fam->spouse_first_name ?? '') . ' ' . ($fam->spouse_name_extension ?? '')));
            $this->set($sheet, 'D38', $fam->spouse_middle_name);
            $this->set($sheet, 'D39', $fam->spouse_occupation);
            $this->set($sheet, 'D40', $fam->spouse_employer_business_name);
            $this->set($sheet, 'D41', $fam->spouse_business_address);
            $this->set($sheet, 'D42', $fam->spouse_telephone_no);

            $this->set($sheet, 'D43', $fam->father_surname);
            $this->set($sheet, 'D44', trim(($fam->father_first_name ?? '') . ' ' . ($fam->father_name_extension ?? '')));
            $this->set($sheet, 'D45', $fam->father_middle_name);

            $this->set($sheet, 'D47', $fam->mother_maiden_surname);
            $this->set($sheet, 'D48', $fam->mother_first_name);
            $this->set($sheet, 'D49', $fam->mother_middle_name);
        }

        $childRow = 37;
        foreach ($user->pdsChildren as $child) {
            if ($childRow > 48) break;
            $this->set($sheet, "I{$childRow}", $child->full_name);
            $this->set($sheet, "M{$childRow}", $child->date_of_birth->format('d/m/Y'));
            $childRow++;
        }

        foreach ($user->pdsEducationalBackgrounds as $edu) {
            $row = self::EDUCATION_ROWS[$edu->level] ?? null;
            if (!$row) continue;

            $this->set($sheet, "D{$row}", $edu->school_name);
            $this->set($sheet, "G{$row}", $edu->degree_course);
            $this->set($sheet, "J{$row}", optional($edu->period_from)->format('Y'));
            $this->set($sheet, "K{$row}", optional($edu->period_to)->format('Y'));
            $this->set($sheet, "L{$row}", $edu->highest_level_units);
            $this->set($sheet, "M{$row}", $edu->year_graduated);
            $this->set($sheet, "N{$row}", $edu->scholarship_honors);
        }
    }

    private function fillEligibilityAndWork(Worksheet $sheet, User $user): void
    {
        $row = 5;
        foreach ($user->pdsCivilServiceEligibilities as $elig) {
            if ($row > 11) break;
            $this->set($sheet, "A{$row}", $elig->eligibility_name);
            $this->set($sheet, "F{$row}", $elig->rating);
            $this->set($sheet, "G{$row}", optional($elig->exam_date)->format('d/m/Y'));
            $this->set($sheet, "I{$row}", $elig->exam_place);
            $this->set($sheet, "L{$row}", $elig->license_number);
            $this->set($sheet, "M{$row}", optional($elig->license_valid_until)->format('d/m/Y'));
            $row++;
        }

        $row = 18;
        foreach ($user->pdsWorkExperiences as $work) {
            if ($row > 45) break;
            $this->set($sheet, "A{$row}", $work->date_from->format('d/m/Y'));
            $this->set($sheet, "C{$row}", $work->date_to ? $work->date_to->format('d/m/Y') : 'Present');
            $this->set($sheet, "D{$row}", $work->position_title);
            $this->set($sheet, "G{$row}", $work->department_agency_office_company);
            $this->set($sheet, "J{$row}", $work->monthly_salary);
            $this->set($sheet, "K{$row}", $work->salary_grade);
            $this->set($sheet, "L{$row}", $work->status_of_appointment);
            $this->set($sheet, "M{$row}", $work->is_government_service ? 'Y' : 'N');
            $row++;
        }
    }

    private function fillVoluntaryTrainingOther(Worksheet $sheet, User $user): void
    {
        $row = 6;
        foreach ($user->pdsVoluntaryWorks as $vw) {
            if ($row > 12) break;
            $this->set($sheet, "A{$row}", $vw->organization_name_address);
            $this->set($sheet, "E{$row}", $vw->date_from->format('d/m/Y'));
            $this->set($sheet, "F{$row}", $vw->date_to ? $vw->date_to->format('d/m/Y') : 'Present');
            $this->set($sheet, "G{$row}", $vw->number_of_hours);
            $this->set($sheet, "H{$row}", $vw->position_nature_of_work);
            $row++;
        }

        $row = 18;
        foreach ($user->pdsTrainings as $t) {
            if ($row > 38) break;
            $this->set($sheet, "A{$row}", $t->title);
            $this->set($sheet, "E{$row}", $t->date_from->format('d/m/Y'));
            $this->set($sheet, "F{$row}", $t->date_to ? $t->date_to->format('d/m/Y') : '');
            $this->set($sheet, "G{$row}", $t->number_of_hours);
            $this->set($sheet, "H{$row}", $t->type);
            $this->set($sheet, "I{$row}", $t->conducted_sponsored_by);
            $row++;
        }

        $other = $user->pdsOtherInformation;
        if ($other) {
            $this->fillListColumn($sheet, 'A', 42, 48, $other->special_skills_hobbies ?? []);
            $this->fillListColumn($sheet, 'C', 42, 48, $other->non_academic_distinctions ?? []);
            $this->fillListColumn($sheet, 'I', 42, 48, $other->membership_associations ?? []);
        }
    }

    private function fillReferencesAndDeclaration(Worksheet $sheet, User $user): void
    {
        $row = 52;
        foreach ($user->pdsReferences as $ref) {
            if ($row > 54) break;
            $this->set($sheet, "A{$row}", $ref->name);
            $this->set($sheet, "F{$row}", $ref->address);
            $this->set($sheet, "G{$row}", $ref->contact_no_email);
            $row++;
        }

        $d = $user->pdsDeclaration;
        if ($d) {
            $this->set($sheet, 'D61', $d->government_id_type);
            $this->set($sheet, 'D62', $d->government_id_no);
            $this->set($sheet, 'D64', $d->id_issuance_date_place);
            $this->set($sheet, 'J65', optional($d->date_accomplished)->format('d/m/Y'));

            if ($d->photo_path && Storage::disk('public')->exists($d->photo_path)) {
                $photo = new Drawing();
                $photo->setPath(storage_path('app/public/' . $d->photo_path));
                $photo->setCoordinates('K58');
                $photo->setWidth(90);
                $photo->setHeight(110);
                $photo->setOffsetX(5);
                $photo->setOffsetY(5);
                $photo->setWorksheet($sheet);
            }

            if ($d->signature_path && Storage::disk('public')->exists($d->signature_path)) {
                $signature = new Drawing();
                $signature->setPath(storage_path('app/public/' . $d->signature_path));
                $signature->setCoordinates('F60');
                $signature->setWidth(140);
                $signature->setHeight(50);
                $signature->setOffsetX(5);
                $signature->setOffsetY(20);
                $signature->setWorksheet($sheet);
            }
        }

        // Note: the "SUBSCRIBED AND SWORN..." / "Person Administering Oath"
        // block (rows 67-69) is intentionally left blank. It requires the
        // actual signature of an authorized administering officer at the
        // time of physical submission and cannot be pre-filled by the system.
    }

    private function fillQuestionnaire(Worksheet $sheet, User $user): void
    {
        $q = $user->pdsQuestionnaire;
        if (!$q) return;

        $this->fillDetailsLine($sheet, 'G10', $q->related_third_degree_details);
        $this->fillDetailsLine($sheet, 'G14', $q->found_admin_guilty_details);
        $this->fillDetailsLine($sheet, 'G19', $q->criminally_charged_details);

        if ($q->criminally_charged_date_filed) {
            $this->set($sheet, 'I20', optional($q->criminally_charged_date_filed)->format('d/m/Y'));
        }
        $this->set($sheet, 'H22', $q->criminally_charged_status);

        $this->fillDetailsLine($sheet, 'G24', $q->convicted_crime_details);
        $this->fillDetailsLine($sheet, 'G28', $q->separated_from_service_details);
        $this->fillDetailsLine($sheet, 'G32', $q->candidate_in_election_details);
        $this->fillDetailsLine($sheet, 'G35', $q->resigned_before_election_details);
        $this->fillDetailsLine($sheet, 'G38', $q->acquired_immigrant_status_country);
        $this->fillDetailsLine($sheet, 'G44', $q->indigenous_group_details);
        $this->fillDetailsLine($sheet, 'G46', $q->pwd_id_no);
        $this->fillDetailsLine($sheet, 'G48', $q->solo_parent_id_no);
    }

    private function fillListColumn(Worksheet $sheet, string $col, int $startRow, int $endRow, array $items): void
    {
        $row = $startRow;
        foreach ($items as $item) {
            if ($row > $endRow) break;
            $this->set($sheet, "{$col}{$row}", $item);
            $row++;
        }
    }

    /**
     * Replaces the underscore blank-line portion of a "give details" prompt
     * with an actual clean sentence, instead of leaving decorative
     * underscores or overwriting the instruction text entirely.
     */
    private function fillDetailsLine(Worksheet $sheet, string $cell, ?string $details): void
    {
        if (empty($details)) {
            return;
        }

        $existing = (string) $sheet->getCell($cell)->getValue();
        $label = trim(preg_replace('/_+/', '', $existing));
        $label = rtrim($label, ': ') . ': ';

        $sheet->setCellValueExplicit($cell, $label . $details, DataType::TYPE_STRING);
        $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
    }

    /**
     * Force a cell to plain text (avoiding date/number auto-coercion) and
     * apply consistent center alignment + font so every value we write
     * matches the template's visual style regardless of that cell's
     * original quirks.
     */
    private function set(Worksheet $sheet, string $cell, $value): void
    {
        $value = $value ?? '';

        $style = $sheet->getStyle($cell);
        $style->getNumberFormat()->setFormatCode('@');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $style->getFont()->setName('Arial')->setSize(7);

        $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
    }
}