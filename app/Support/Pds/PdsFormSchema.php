<?php

namespace App\Support\Pds;

/**
 * What the on-screen PDS asks for, section by section.
 *
 * Mirrors CS Form 212 (Revised 2026), in the form's own numbering, so an
 * employee who has filled in the paper version recognises every question.
 * Where each answer is printed is a separate concern: see PdsWorkbookGenerator.
 *
 * Answers are stored under the section key, e.g. form_data['personal']['surname']
 * or form_data['work']['items'][0]['position'].
 */
class PdsFormSchema
{
    public const SEX = ['male' => 'Male', 'female' => 'Female'];

    public const CIVIL_STATUS = [
        'single' => 'Single',
        'married' => 'Married',
        'widowed' => 'Widowed',
        'separated' => 'Separated',
        'other' => 'Other/s',
    ];

    public const CITIZENSHIP = ['filipino' => 'Filipino', 'dual' => 'Dual Citizenship'];

    public const DUAL_BY = ['birth' => 'By birth', 'naturalization' => 'By naturalization'];

    public const YES_NO = ['yes' => 'Yes', 'no' => 'No'];

    public const EDUCATION_LEVELS = [
        'elementary' => 'Elementary',
        'secondary' => 'Secondary',
        'vocational' => 'Vocational / Trade Course',
        'college' => 'College',
        'graduate' => 'Graduate Studies',
    ];

    /** Rows the official page has for children; more need a separate sheet. */
    public const MAX_CHILDREN = 12;

    /** The form has room for exactly three references. */
    public const MAX_REFERENCES = 3;

    /**
     * Questions 34 to 40: the YES/NO items on page 4, and what a YES must
     * be followed by.
     *
     * @return array<string, array{number: string, text: string, details: array<string, string>}>
     */
    public const QUESTIONS = [
        'q34a' => [
            'number' => '34a',
            'text' => 'Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau or office or to the person who has immediate supervision over you in the Office, Bureau or Department where you will be appointed — within the third degree?',
            'details' => [],
        ],
        'q34b' => [
            'number' => '34b',
            'text' => 'Within the fourth degree (for Local Government Unit – Career Employees)?',
            'details' => ['q34_details' => 'Details'],
        ],
        'q35a' => [
            'number' => '35a',
            'text' => 'Have you ever been found guilty of any administrative offense?',
            'details' => ['q35a_details' => 'Details'],
        ],
        'q35b' => [
            'number' => '35b',
            'text' => 'Have you been criminally charged before any court?',
            'details' => ['q35b_date_filed' => 'Date filed', 'q35b_status' => 'Status of case/s'],
        ],
        'q36' => [
            'number' => '36',
            'text' => 'Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?',
            'details' => ['q36_details' => 'Details'],
        ],
        'q37' => [
            'number' => '37',
            'text' => 'Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition) in the public or private sector?',
            'details' => ['q37_details' => 'Details'],
        ],
        'q38a' => [
            'number' => '38a',
            'text' => 'Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?',
            'details' => ['q38a_details' => 'Details'],
        ],
        'q38b' => [
            'number' => '38b',
            'text' => 'Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?',
            'details' => ['q38b_details' => 'Details'],
        ],
        'q39' => [
            'number' => '39',
            'text' => 'Have you acquired the status of an immigrant or permanent resident of another country?',
            'details' => ['q39_details' => 'Country'],
        ],
        'q40a' => [
            'number' => '40a',
            'text' => 'Are you a member of any indigenous group?',
            'details' => ['q40a_details' => 'Please specify'],
        ],
        'q40b' => [
            'number' => '40b',
            'text' => 'Are you a person with disability?',
            'details' => ['q40b_details' => 'PWD ID no.'],
        ],
        'q40c' => [
            'number' => '40c',
            'text' => 'Are you a solo parent?',
            'details' => ['q40c_details' => 'Solo parent ID no.'],
        ],
    ];

    /** @return array<string, array{title: string, page: string, number: string, intro: string}> */
    public static function sections(): array
    {
        return [
            'personal' => [
                'title' => 'Personal Information',
                'page' => 'C1',
                'number' => 'I',
                'intro' => 'Items 1 to 21. Write names in full; do not abbreviate.',
            ],
            'family' => [
                'title' => 'Family Background',
                'page' => 'C1',
                'number' => 'II',
                'intro' => 'Items 22 to 25. Leave a person out entirely if not applicable; the form prints N/A.',
            ],
            'education' => [
                'title' => 'Educational Background',
                'page' => 'C1',
                'number' => 'III',
                'intro' => 'Item 26. Fill in each level you attended.',
            ],
            'eligibility' => [
                'title' => 'Civil Service Eligibility',
                'page' => 'C2',
                'number' => 'IV',
                'intro' => 'Item 27. CES/CSEE/Career Service/RA 1080 (Board/Bar)/Under special laws/Category II/IV eligibility and eligibilities for uniformed personnel.',
            ],
            'work' => [
                'title' => 'Work Experience',
                'page' => 'C2',
                'number' => 'V',
                'intro' => 'Item 28. Include private employment. The form lists your most recent work first.',
            ],
            'voluntary' => [
                'title' => 'Voluntary Work',
                'page' => 'C3',
                'number' => 'VI',
                'intro' => 'Item 29. Involvement in civic, non-government, people or voluntary organizations.',
            ],
            'learning' => [
                'title' => 'Learning and Development',
                'page' => 'C3',
                'number' => 'VII',
                'intro' => 'Item 30. Interventions and training programs attended. The form lists the most recent first.',
            ],
            'other' => [
                'title' => 'Other Information',
                'page' => 'C3',
                'number' => 'VIII',
                'intro' => 'Items 31 to 33. One entry per line.',
            ],
            'questions' => [
                'title' => 'Questions 34 to 40',
                'page' => 'C4',
                'number' => 'IX',
                'intro' => 'Answer every question. A YES needs its details.',
            ],
            'references' => [
                'title' => 'References and ID',
                'page' => 'C4',
                'number' => 'X',
                'intro' => 'Items 41 and 42. References must not be related to you by consanguinity or affinity.',
            ],
        ];
    }

    public static function has(string $section): bool
    {
        return array_key_exists($section, self::sections());
    }

    /**
     * The repeating tables, per section.
     *
     * Each list has a key column: a row without it is not an entry. `max` is
     * how many the system accepts in total; rows beyond what the page holds
     * are printed on a continuation page.
     *
     * @return array<string, array<string, array{label: string, item: string, max: int, key: string, columns: array<string, array>}>>
     */
    public static function lists(): array
    {
        $text = fn (string $label, int $max = 255, array $extra = []) => ['label' => $label, 'type' => 'text', 'rules' => ['nullable', 'string', "max:{$max}"], ...$extra];
        $date = fn (string $label, array $extra = []) => ['label' => $label, 'type' => 'date', 'rules' => ['nullable', 'date'], ...$extra];
        $hours = ['label' => 'Hours', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0', 'max:99999'], 'width' => 'narrow'];

        return [
            'eligibility' => [
                'items' => [
                    'label' => 'Eligibilities', 'item' => 'eligibility', 'max' => 50, 'key' => 'name',
                    'columns' => [
                        'name' => $text('Eligibility', 255, ['wide' => true, 'placeholder' => 'e.g. Career Service Professional']),
                        'rating' => $text('Rating', 20, ['width' => 'narrow']),
                        'exam_date' => $date('Date of exam / conferment'),
                        'exam_place' => $text('Place of exam / conferment'),
                        'license_number' => $text('License no.', 50),
                        'license_valid_until' => $date('Valid until'),
                    ],
                ],
            ],
            'work' => [
                'items' => [
                    'label' => 'Positions held', 'item' => 'position', 'max' => 100, 'key' => 'position',
                    'columns' => [
                        'from' => $date('From', ['rules' => ['required_with:items.*.position', 'nullable', 'date']]),
                        'to' => $date('To', ['rules' => ['nullable', 'date', 'after_or_equal:items.*.from'], 'hint' => 'Leave blank if this is your present position']),
                        'position' => $text('Position title', 255, ['wide' => true, 'hint' => 'Write in full; do not abbreviate']),
                        'department' => $text('Department / agency / office / company', 255, ['wide' => true]),
                        'salary' => ['label' => 'Monthly salary', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0', 'max:9999999']],
                        'grade' => $text('Salary grade & step', 20, ['placeholder' => 'e.g. 11-1']),
                        'status' => $text('Status of appointment', 60, ['placeholder' => 'e.g. Permanent']),
                        'government' => ['label' => "Gov't service", 'type' => 'select', 'options' => ['Y' => 'Yes', 'N' => 'No'], 'rules' => ['nullable', 'in:Y,N']],
                    ],
                ],
            ],
            'voluntary' => [
                'items' => [
                    'label' => 'Voluntary work', 'item' => 'organization', 'max' => 50, 'key' => 'organization',
                    'columns' => [
                        'organization' => $text('Name & address of organization', 255, ['wide' => true]),
                        'from' => $date('From'),
                        'to' => $date('To', ['rules' => ['nullable', 'date', 'after_or_equal:items.*.from']]),
                        'hours' => $hours,
                        'position' => $text('Position / nature of work', 255, ['wide' => true]),
                    ],
                ],
            ],
            'learning' => [
                'items' => [
                    'label' => 'Trainings attended', 'item' => 'training', 'max' => 200, 'key' => 'title',
                    'columns' => [
                        'title' => $text('Title of L&D intervention / training program', 255, ['wide' => true]),
                        'from' => $date('From'),
                        'to' => $date('To', ['rules' => ['nullable', 'date', 'after_or_equal:items.*.from']]),
                        'hours' => $hours,
                        'type' => $text('Type of L&D', 40, ['placeholder' => 'Managerial / Supervisory / Technical']),
                        'sponsor' => $text('Conducted / sponsored by', 255, ['wide' => true]),
                    ],
                ],
            ],
            'other' => [
                'skills' => [
                    'label' => '31. Special skills and hobbies', 'item' => 'skill', 'max' => 50, 'key' => 'text',
                    'columns' => ['text' => $text('Skill or hobby', 150, ['wide' => true])],
                ],
                'distinctions' => [
                    'label' => '32. Non-academic distinctions / recognition', 'item' => 'distinction', 'max' => 50, 'key' => 'text',
                    'columns' => ['text' => $text('Distinction or recognition (write in full)', 255, ['wide' => true])],
                ],
                'memberships' => [
                    'label' => '33. Membership in association / organization', 'item' => 'membership', 'max' => 50, 'key' => 'text',
                    'columns' => ['text' => $text('Association or organization (write in full)', 255, ['wide' => true])],
                ],
            ],
            'references' => [
                'references' => [
                    'label' => '41. References', 'item' => 'reference', 'max' => self::MAX_REFERENCES, 'key' => 'name',
                    'columns' => [
                        'name' => $text('Name', 150, ['wide' => true]),
                        'address' => $text('Office / residential address', 255, ['wide' => true]),
                        'contact' => $text('Contact no. and/or e-mail', 100),
                    ],
                ],
            ],
        ];
    }

    /** Laravel validation rules for one section's input. */
    public static function rules(string $section): array
    {
        $text = ['nullable', 'string', 'max:255'];
        $short = ['nullable', 'string', 'max:60'];
        $date = ['nullable', 'date', 'before_or_equal:today'];

        $rules = match ($section) {
            'personal' => [
                'surname' => ['required', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:100'],
                'name_extension' => ['nullable', 'string', 'max:10'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'date_of_birth' => ['required', 'date', 'before:today'],
                'place_of_birth' => $text,
                'sex' => ['required', 'in:' . implode(',', array_keys(self::SEX))],
                'civil_status' => ['required', 'in:' . implode(',', array_keys(self::CIVIL_STATUS))],
                'civil_status_other' => ['nullable', 'required_if:civil_status,other', 'string', 'max:40'],
                'height' => ['nullable', 'numeric', 'between:0.5,2.5'],
                'weight' => ['nullable', 'numeric', 'between:10,400'],
                'blood_type' => ['nullable', 'string', 'max:5'],
                'umid' => $short,
                'pagibig' => $short,
                'philhealth' => $short,
                'philsys' => $short,
                'tin' => $short,
                'agency_employee_no' => $short,
                'citizenship' => ['required', 'in:' . implode(',', array_keys(self::CITIZENSHIP))],
                'dual_by' => ['nullable', 'required_if:citizenship,dual', 'in:' . implode(',', array_keys(self::DUAL_BY))],
                'dual_country' => ['nullable', 'required_if:citizenship,dual', 'string', 'max:60'],
                ...self::addressRules('residential'),
                ...self::addressRules('permanent'),
                'telephone' => $short,
                'mobile' => $short,
                'email' => ['nullable', 'email', 'max:255'],
            ],
            'family' => [
                'spouse.surname' => $text,
                'spouse.first_name' => $text,
                'spouse.name_extension' => ['nullable', 'string', 'max:10'],
                'spouse.middle_name' => $text,
                'spouse.occupation' => $text,
                'spouse.employer' => $text,
                'spouse.business_address' => $text,
                'spouse.telephone' => $short,
                'father.surname' => $text,
                'father.first_name' => $text,
                'father.name_extension' => ['nullable', 'string', 'max:10'],
                'father.middle_name' => $text,
                'mother.surname' => $text,
                'mother.first_name' => $text,
                'mother.middle_name' => $text,
                'children' => ['nullable', 'array', 'max:' . self::MAX_CHILDREN],
                'children.*.name' => ['required', 'string', 'max:150'],
                'children.*.date_of_birth' => $date,
            ],
            'education' => collect(array_keys(self::EDUCATION_LEVELS))
                ->flatMap(fn ($level) => [
                    "{$level}.school" => $text,
                    "{$level}.degree" => $text,
                    "{$level}.from" => ['nullable', 'digits:4'],
                    "{$level}.to" => ['nullable', 'digits:4'],
                    "{$level}.units" => ['nullable', 'string', 'max:60'],
                    "{$level}.year_graduated" => ['nullable', 'digits:4'],
                    "{$level}.honors" => $text,
                ])->all(),
            'questions' => collect(self::QUESTIONS)
                ->flatMap(fn ($question, $key) => [
                    $key => ['required', 'in:yes,no'],
                    ...collect($question['details'])->mapWithKeys(fn ($label, $field) => [
                        $field => $field === 'q35b_date_filed'
                            ? ['nullable', 'date', 'before_or_equal:today']
                            : ['nullable', 'string', 'max:120'],
                    ])->all(),
                ])->all(),
            'references' => [
                'government_id.type' => ['nullable', 'string', 'max:60'],
                'government_id.number' => ['nullable', 'string', 'max:60'],
                'government_id.issued' => ['nullable', 'string', 'max:120'],
            ],
            default => [],
        };

        // Every repeating table in the section.
        foreach (self::lists()[$section] ?? [] as $list => $definition) {
            $rules[$list] = ['nullable', 'array', 'max:' . $definition['max']];

            foreach ($definition['columns'] as $column => $spec) {
                $columnRules = $spec['rules'];

                // A row exists because its key column is filled; the list is
                // cleaned of blank rows before validation.
                if ($column === $definition['key']) {
                    $columnRules = ['required', ...array_values(array_diff($columnRules, ['nullable']))];
                }

                $rules["{$list}.*.{$column}"] = array_map(
                    fn ($rule) => is_string($rule) ? str_replace('items.*.', "{$list}.*.", $rule) : $rule,
                    $columnRules,
                );
            }
        }

        return $rules;
    }

    /** Messages that read better than Laravel's generic ones. */
    public static function messages(string $section): array
    {
        if ($section !== 'questions') {
            return [];
        }

        return collect(self::QUESTIONS)
            ->mapWithKeys(fn ($q, $key) => ["{$key}.required" => "Answer question {$q['number']}."])
            ->all();
    }

    /** Friendlier names for validation messages. */
    public static function attributes(string $section): array
    {
        $attributes = match ($section) {
            'personal' => [
                'civil_status_other' => 'civil status (other)',
                'dual_by' => 'how dual citizenship was acquired',
                'dual_country' => 'country of dual citizenship',
                'umid' => 'UMID ID no.',
                'pagibig' => 'PAG-IBIG ID no.',
                'philhealth' => 'PhilHealth no.',
                'philsys' => 'PhilSys number',
                'tin' => 'TIN',
            ],
            'family' => [
                'children.*.name' => 'child\'s name',
                'children.*.date_of_birth' => 'child\'s date of birth',
            ],
            'education' => collect(self::EDUCATION_LEVELS)->flatMap(fn ($label, $level) => [
                "{$level}.from" => strtolower($label) . ' "from" year',
                "{$level}.to" => strtolower($label) . ' "to" year',
                "{$level}.year_graduated" => strtolower($label) . ' year graduated',
            ])->all(),
            'questions' => collect(self::QUESTIONS)->flatMap(fn ($q, $key) => [
                $key => "question {$q['number']}",
                ...collect($q['details'])->mapWithKeys(fn ($label, $field) => [$field => strtolower($label) . " for question {$q['number']}"])->all(),
            ])->all(),
            default => [],
        };

        foreach (self::lists()[$section] ?? [] as $list => $definition) {
            foreach ($definition['columns'] as $column => $spec) {
                $attributes["{$list}.*.{$column}"] = strtolower($spec['label']);
            }
        }

        return $attributes;
    }

    /**
     * Details a YES must come with, beyond what the rules can express: a
     * question answered YES whose details were left blank.
     *
     * @return array<string, string> field => message
     */
    public static function missingDetails(array $answers): array
    {
        $missing = [];

        foreach (self::QUESTIONS as $key => $question) {
            // Question 34 has one details line shared by its two parts.
            $answeredYes = $key === 'q34b'
                ? (($answers['q34a'] ?? null) === 'yes' || ($answers['q34b'] ?? null) === 'yes')
                : ($answers[$key] ?? null) === 'yes';

            if (! $answeredYes) {
                continue;
            }

            foreach ($question['details'] as $field => $label) {
                if (! filled($answers[$field] ?? null)) {
                    $number = $key === 'q34b' ? '34' : $question['number'];
                    $missing[$field] = "You answered YES to question {$number}; give the " . strtolower($label) . '.';
                }
            }
        }

        return $missing;
    }

    /** @return array<string, string> the parts of an address, in form order */
    public static function addressParts(): array
    {
        return [
            'house' => 'House/Block/Lot No.',
            'street' => 'Street',
            'subdivision' => 'Subdivision/Village',
            'barangay' => 'Barangay',
            'city' => 'City/Municipality',
            'province' => 'Province',
            'zip' => 'ZIP Code',
        ];
    }

    private static function addressRules(string $prefix): array
    {
        $rules = [];

        foreach (array_keys(self::addressParts()) as $part) {
            $rules["{$prefix}.{$part}"] = $part === 'zip'
                ? ['nullable', 'string', 'max:10']
                : ['nullable', 'string', 'max:150'];
        }

        return $rules;
    }
}
