<?php
namespace App\Services;

class PdsWizardSteps
{
    public const STEPS = [
        1 => ['key' => 'personal', 'label' => 'Personal Information'],
        2 => ['key' => 'family', 'label' => 'Family Background'],
        3 => ['key' => 'education', 'label' => 'Educational Background'],
        4 => ['key' => 'eligibility', 'label' => 'Civil Service Eligibility'],
        5 => ['key' => 'work', 'label' => 'Work Experience'],
        6 => ['key' => 'voluntary', 'label' => 'Voluntary Work'],
        7 => ['key' => 'training', 'label' => 'Learning & Development'],
        8 => ['key' => 'other', 'label' => 'Other Information'],
        9 => ['key' => 'questionnaire', 'label' => 'Background Questions'],
        10 => ['key' => 'references', 'label' => 'References'],
        11 => ['key' => 'declaration', 'label' => 'Declaration & Submit'],
    ];

    public static function total(): int
    {
        return count(self::STEPS);
    }

    public static function label(int $step): string
    {
        return self::STEPS[$step]['label'] ?? 'Unknown Step';
    }

    public static function key(int $step): string
    {
        return self::STEPS[$step]['key'] ?? '';
    }
}