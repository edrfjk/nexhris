<?php

namespace App\Services\LeaveForm;

use App\Services\Xlsx\TemplateFiller;

/**
 * Where everything sits on the campus CS Form No. 6 (Revised 2020).
 *
 * The form's designer left an invisible text box over every answer line —
 * the office, the three parts of the name, each "(Specify)" line, the lines
 * under 7.B and 7.D — so a person filling it in Excel types into those. The
 * system writes into the same boxes, so a printed answer lands exactly where
 * a typed one would. The tick boxes are legacy form controls without
 * captions, so they are found by shape id.
 *
 * Page 1 is the form, page 2 the instructions; they are found by position,
 * not by tab name, because the tab names carry the revision year.
 */
final class CsForm6
{
    /** Labels that must be where this layout puts them: [cell, text]. */
    public const LAYOUT_CHECKS = [
        ['A3', 'APPLICATION FOR LEAVE'],
        ['A4', 'OFFICE/DEPARTMENT'],
        ['E4', 'NAME'],
        ['A9', '6.A'],
        ['G9', '6.B'],
        ['C11', 'Vacation Leave'],
        ['C35', 'Adoption Leave'],
        ['I39', 'Monetization'],
        ['A43', '6.C'],
        ['G43', '6.D'],
        ['A51', '7.A'],
        ['G51', '7.B'],
        ['D55', 'Vacation Leave'],
        ['E55', 'Sick Leave'],
        ['C56', 'Total Earned'],
        ['A61', '7.C'],
        ['G61', '7.D'],
    ];

    /** The answer text boxes, by what goes in them. */
    public const BOXES = [
        'office' => 'TextBox 66',
        'last_name' => 'TextBox 6',
        'first_name' => 'TextBox 1',
        'middle_name' => 'TextBox 3',
        'date_filed' => 'TextBox 38',
        'position' => 'TextBox 39',
        'salary' => 'TextBox 40',
        'within_philippines' => 'TextBox 41',
        'abroad' => 'TextBox 42',
        'in_hospital' => 'TextBox 43',
        'out_patient' => 'TextBox 44',
        'sick_more' => 'TextBox 45',
        'women_illness' => 'TextBox 46',
        'women_more' => 'TextBox 47',
        'others' => 'TextBox 48',
        'working_days' => 'TextBox 49',
        'inclusive_dates' => 'TextBox 50',
        'as_of' => 'TextBox 51',
        'disapproval_1' => 'TextBox 52',
        'disapproval_2' => 'TextBox 53',
        'disapproval_3' => 'TextBox 54',
        'disapproval_4' => 'TextBox 55',
        'days_with_pay' => 'TextBox 59',
        'days_without_pay' => 'TextBox 60',
        'others_approved' => 'TextBox 61',
        'disapproved_1' => 'TextBox 62',
        'disapproved_2' => 'TextBox 63',
        'disapproved_3' => 'TextBox 64',
        // An empty box over the Campus Director's block, never written.
        'director_space' => 'TextBox 65',
    ];

    /**
     * Boxes whose bottom edge the designer left short of the ruled line, and
     * where it belongs, in EMU into the row the box ends in.
     */
    public const BOX_BOTTOMS = [
        'date_filed' => 312420,     // level with "4. POSITION"'s box
        'salary' => 312420,
        'disapproval_1' => 215900,  // on "For disapproval due to ____"
    ];

    /** 6.A: the tick box beside each type of leave. */
    public const TYPE_BOXES = [
        'VL' => 1029,
        'FL' => 1027,
        'SL' => 1028,
        'ML' => 1030,
        'PL' => 1031,
        'SPL' => 1032,
        'SOLO' => 1033,
        'STUDY' => 1034,
        'VAWC' => 1035,
        'RL' => 1036,
        'SLBW' => 1037,
        'SEL' => 1038,
        'ADOPT' => 1039,
    ];

    /** 6.B, 6.D and 7.B tick boxes. */
    public const DETAIL_BOXES = [
        'within_philippines' => 1040,
        'abroad' => 1041,
        'in_hospital' => 1042,
        'out_patient' => 1043,
        'masters' => 1044,
        'bar_review' => 1045,
        'monetization' => 1046,
        'terminal' => 1047,
        'not_requested' => 1048,
        'requested' => 1049,
        'for_approval' => 1050,
        'for_disapproval' => 1051,
    ];

    /** 7.A: the certification table. */
    public const CREDIT_CELLS = [
        'vl' => ['earned' => 'D56', 'less' => 'D57', 'balance' => 'D58'],
        'sl' => ['earned' => 'E56', 'less' => 'E57', 'balance' => 'E58'],
    ];

    /** Over "(Signature of Applicant)". */
    public const APPLICANT_CELL = 'I48';

    /** Over "Immediate Supervisor/Head" — the Dean of the applicant's college. */
    public const SUPERVISOR_CELL = 'I59';

    /** The form page and the instructions page, by position. */
    public static function sheets(TemplateFiller $filler): array
    {
        $names = $filler->sheetNames();

        return [$names[0] ?? null, $names[1] ?? null];
    }

    /** Whether this workbook is laid out as the form this class describes. */
    public static function matches(TemplateFiller $filler): bool
    {
        [$form] = self::sheets($filler);

        if ($form === null) {
            return false;
        }

        foreach (self::LAYOUT_CHECKS as [$cell, $expected]) {
            $actual = preg_replace('/\s+/', ' ', $filler->getCellText($form, $cell));

            if (! str_contains(mb_strtolower($actual), mb_strtolower($expected))) {
                return false;
            }
        }

        foreach (self::BOXES as $box) {
            if (! $filler->hasShape($form, $box)) {
                return false;
            }
        }

        return true;
    }
}
