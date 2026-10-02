<?php

namespace App\Support\Leave;

/**
 * The kinds of leave CS Form No. 6 can be filed for, and what page 2 of the
 * form — "Instructions and Requirements" — says about each.
 *
 * The thirteen types of 6.A come first, in the form's own order and with its
 * own legal citations. Monetization and terminal leave are filed through
 * 6.B's "Other purpose" boxes. Anything else goes on 6.A's "Others" line;
 * service credits and wellness leave are named there because the campus
 * ledger keeps credits for them.
 */
final class LeaveTypes
{
    /** Where on the form a type is ticked. */
    public const FORM = 'form';

    public const OTHER_PURPOSE = 'other_purpose';

    public const OTHERS = 'others';

    /** What 6.B asks for. */
    public const DETAIL_LOCATION = 'location';

    public const DETAIL_SICKNESS = 'sickness';

    public const DETAIL_WOMEN = 'women';

    public const DETAIL_STUDY = 'study';

    /**
     * code => definition
     *
     *  label      as printed on the form
     *  citation   the form's own reference beside it
     *  group      FORM, OTHER_PURPOSE or OTHERS
     *  charge     the credits it draws on: vl, sl, service, or null
     *  detail     what 6.B asks for, or null
     *  max_days   most working days (calendar days where `calendar` is set)
     *  max_months most calendar months a single filing may span
     *  per_year   most days in a calendar year, all filings together
     *  once_a_year only one filing a year
     *  advance    calendar days ahead it should be filed, "whenever possible"
     *  days_only  no inclusive dates; the employee states the days
     *  documents  what must accompany it (page 2)
     *  instruction the page 2 rule, shortened, shown beside the choice
     */
    public const ALL = [
        'VL' => [
            'label' => 'Vacation Leave',
            'citation' => 'Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292',
            'group' => self::FORM,
            'charge' => 'vl',
            'detail' => self::DETAIL_LOCATION,
            'advance' => 5,
            'documents' => [],
            'instruction' => 'File five (5) days in advance whenever possible, and say whether it is within the Philippines or abroad.',
        ],
        'FL' => [
            'label' => 'Mandatory/Forced Leave',
            'citation' => 'Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292',
            'group' => self::FORM,
            'charge' => 'vl',
            'detail' => null,
            'documents' => [],
            'instruction' => 'The annual five-day vacation leave, charged against vacation leave credits.',
        ],
        'SL' => [
            'label' => 'Sick Leave',
            'citation' => 'Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292',
            'group' => self::FORM,
            'charge' => 'sl',
            'detail' => self::DETAIL_SICKNESS,
            'documents' => [],
            'instruction' => 'File immediately upon your return. Filed in advance or longer than five (5) days, it needs a medical certificate (or an affidavit if you did not see a doctor).',
        ],
        'ML' => [
            'label' => 'Maternity Leave',
            'citation' => 'R.A. No. 11210 / IRR issued by CSC, DOLE and SSS',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'max_days' => 105,
            'calendar' => true,
            'documents' => ['Proof of pregnancy, e.g. ultrasound or a doctor\'s certificate on the expected date of delivery', 'Notice of Allocation of Maternity Leave Credits (CS Form No. 6a), if needed'],
            'instruction' => 'Up to 105 days, with proof of pregnancy.',
        ],
        'PL' => [
            'label' => 'Paternity Leave',
            'citation' => 'R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'max_days' => 7,
            'documents' => ['Proof of the child\'s delivery, e.g. birth certificate or medical certificate, and the marriage contract'],
            'instruction' => 'Up to seven (7) days, with proof of the child\'s delivery and the marriage contract.',
        ],
        'SPL' => [
            'label' => 'Special Privilege Leave',
            'citation' => 'Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292',
            'group' => self::FORM,
            'charge' => null,
            'detail' => self::DETAIL_LOCATION,
            'max_days' => 3,
            'per_year' => 3,
            'advance' => 7,
            'documents' => [],
            'instruction' => 'Up to three (3) days a year. File at least one (1) week before, except in emergencies, and say whether it is within the Philippines or abroad.',
        ],
        'SOLO' => [
            'label' => 'Solo Parent Leave',
            'citation' => 'RA No. 8972 / CSC MC No. 8, s. 2004',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'max_days' => 7,
            'per_year' => 7,
            'advance' => 5,
            'documents' => ['Updated Solo Parent Identification Card'],
            'instruction' => 'Up to seven (7) days a year. File five (5) days before whenever possible, with your updated Solo Parent ID.',
        ],
        'STUDY' => [
            'label' => 'Study Leave',
            'citation' => 'Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292',
            'group' => self::FORM,
            'charge' => null,
            'detail' => self::DETAIL_STUDY,
            'max_months' => 6,
            'documents' => ['Contract between the agency head or authorized representative and you'],
            'instruction' => 'Up to six (6) months, under a contract with the agency head.',
        ],
        'VAWC' => [
            'label' => '10-Day VAWC Leave',
            'citation' => 'RA No. 9262 / CSC MC No. 15, s. 2005',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'max_days' => 10,
            'documents' => ['A Barangay Protection Order (BPO), a Temporary/Permanent Protection Order (TPO/PPO), a certification that one was applied for, or a police report with a medical certificate'],
            'instruction' => 'Up to ten (10) days, with a protection order or one of the other supporting documents.',
        ],
        'RL' => [
            'label' => 'Rehabilitation Privilege',
            'citation' => 'Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'max_months' => 6,
            'documents' => ['Letter request supported by relevant reports, such as the police report', 'Medical certificate on the injuries, treatment and the need for rest and rehabilitation', 'Written concurrence of a government physician, if the attending physician is private'],
            'instruction' => 'Up to six (6) months. Apply within one (1) week of the accident.',
        ],
        'SLBW' => [
            'label' => 'Special Leave Benefits for Women',
            'citation' => 'RA No. 9710 / CSC MC No. 25, s. 2010',
            'group' => self::FORM,
            'charge' => null,
            'detail' => self::DETAIL_WOMEN,
            'max_months' => 2,
            'advance' => 5,
            'documents' => ['Medical certificate from the attending surgeon, with a clinical summary, the histopathological report, the operative technique, the duration of the surgery and the estimated recuperation'],
            'instruction' => 'Up to two (2) months. File at least five (5) days before the surgery, or on your return in an emergency.',
        ],
        'SEL' => [
            'label' => 'Special Emergency (Calamity) Leave',
            'citation' => 'CSC MC No. 2, s. 2012, as amended',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'max_days' => 5,
            'once_a_year' => true,
            'documents' => [],
            'instruction' => 'Up to five (5) days, within thirty (30) days of the calamity, once a year.',
        ],
        'ADOPT' => [
            'label' => 'Adoption Leave',
            'citation' => 'R.A. No. 8552',
            'group' => self::FORM,
            'charge' => null,
            'detail' => null,
            'documents' => ['Authenticated copy of the Pre-Adoptive Placement Authority issued by the DSWD'],
            'instruction' => 'With the DSWD Pre-Adoptive Placement Authority.',
        ],
        'MONETIZE' => [
            'label' => 'Monetization of Leave Credits',
            'citation' => null,
            'group' => self::OTHER_PURPOSE,
            'charge' => 'vl',
            'detail' => null,
            'days_only' => true,
            'documents' => ['Letter request to the head of the agency stating valid and justifiable reasons (for 50% or more of your credits)'],
            'instruction' => 'Monetizing fifty percent (50%) or more of your credits needs a letter request to the head of the agency.',
        ],
        'TERMINAL' => [
            'label' => 'Terminal Leave',
            'citation' => null,
            'group' => self::OTHER_PURPOSE,
            'charge' => null,
            'detail' => null,
            'days_only' => true,
            'documents' => ['Proof of resignation, retirement or separation from the service', 'Clearance from money, property and work-related accountabilities'],
            'instruction' => 'With proof of resignation, retirement or separation, and your clearance.',
        ],
        'SERVICE' => [
            'label' => 'Service Credits',
            'citation' => null,
            'group' => self::OTHERS,
            'charge' => 'service',
            'detail' => null,
            'documents' => [],
            'instruction' => 'Charged against your service credits. Printed on the form\'s "Others" line.',
        ],
        'WELLNESS' => [
            'label' => 'Wellness Leave',
            'citation' => null,
            'group' => self::OTHERS,
            'charge' => null,
            'detail' => null,
            'documents' => [],
            'instruction' => 'Printed on the form\'s "Others" line.',
        ],
        'OTHERS' => [
            'label' => 'Others',
            'citation' => null,
            'group' => self::OTHERS,
            'charge' => null,
            'detail' => null,
            'documents' => [],
            'instruction' => 'Say what the leave is for. It is printed on the form\'s "Others" line.',
        ],
    ];

    /** 6.B's choices. */
    public const LOCATIONS = ['within' => 'Within the Philippines', 'abroad' => 'Abroad'];

    public const SICKNESS = ['hospital' => 'In Hospital', 'outpatient' => 'Out Patient'];

    public const STUDY = ['masters' => 'Completion of Master\'s Degree', 'bar' => 'BAR/Board Examination Review'];

    public const COMMUTATION = ['not_requested' => 'Not Requested', 'requested' => 'Requested'];

    /** @return array<string, string> code => label */
    public static function labels(): array
    {
        return array_map(fn (array $type) => $type['label'], self::ALL);
    }

    public static function has(?string $code): bool
    {
        return $code !== null && isset(self::ALL[$code]);
    }

    public static function get(string $code): array
    {
        return self::ALL[$code] + [
            'max_days' => null, 'max_months' => null, 'per_year' => null, 'once_a_year' => false,
            'advance' => null, 'days_only' => false, 'calendar' => false,
        ];
    }

    public static function label(?string $code): string
    {
        return self::ALL[$code]['label'] ?? (string) $code;
    }

    /** @return array<string, array> the types in one group, code => definition */
    public static function inGroup(string $group): array
    {
        return array_filter(self::ALL, fn (array $type) => $type['group'] === $group);
    }

    /** Credits a type draws on: vl, sl, service, or null. */
    public static function charge(?string $code): ?string
    {
        return self::ALL[$code]['charge'] ?? null;
    }
}
