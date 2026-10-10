<?php

namespace App\Services\Leave;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * The one place leave days are counted from a pair of dates.
 *
 * Holidays come off only for the leave types in HOLIDAY_AWARE_TYPES, each
 * approved by HR as a working-day leave:
 *
 *  - VL, SL  ordinary vacation and sick leave (CSC MC No. 41, s. 1998, as
 *            amended by MC No. 14, s. 1999; RA 2625)
 *  - FL      the mandatory five days, which are vacation leave
 *  - SPL     special privilege leave, in place of working days
 *  - SOLO    solo parent leave, "seven (7) working days" (RA 8972, as amended
 *            by RA 11861)
 *  - SEL     calamity leave, five working days within thirty days
 *
 * Every other type keeps the rule it had — calendar days where the type says
 * so, otherwise Monday to Friday without holiday exclusion — until HR
 * confirms its treatment.
 */
class WorkingDayCalculator
{
    public const HOLIDAY_AWARE_TYPES = ['VL', 'SL', 'FL', 'SPL', 'SOLO', 'SEL'];

    /**
     * ISO weekdays of the campus schedule: Monday to Friday for teaching and
     * non-teaching personnel alike. An asynchronous Friday is still a working
     * day, so it is charged like any other.
     */
    public const STANDARD_WEEK = [1, 2, 3, 4, 5];

    /**
     * @return array{days: int, holidays: list<array{date: string, name: string}>}
     */
    public function breakdown(
        CarbonInterface $from,
        CarbonInterface $to,
        bool $excludeHolidays = true,
    ): array {
        $from = Carbon::parse($from->toDateString(), config('app.timezone'))->startOfDay();
        $to = Carbon::parse($to->toDateString(), config('app.timezone'))->startOfDay();

        if ($to->lt($from)) {
            return ['days' => 0, 'holidays' => []];
        }

        $holidays = $excludeHolidays ? $this->excludedHolidays($from, $to) : [];
        $days = 0;
        $skipped = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
            if (! in_array($date->dayOfWeekIso, self::STANDARD_WEEK, true)) {
                continue;
            }

            $key = $date->toDateString();

            if (isset($holidays[$key])) {
                // Only a holiday on a day that would otherwise be worked is
                // worth telling the employee about.
                $skipped[] = ['date' => $key, 'name' => $holidays[$key]];

                continue;
            }

            $days++;
        }

        return ['days' => $days, 'holidays' => $skipped];
    }

    public function days(CarbonInterface $from, CarbonInterface $to, bool $excludeHolidays = true): int
    {
        return $this->breakdown($from, $to, $excludeHolidays)['days'];
    }

    public static function excludesHolidaysFor(?string $leaveType): bool
    {
        return in_array($leaveType, self::HOLIDAY_AWARE_TYPES, true);
    }

    /** @return array<string, string> date => name(s), one entry per date */
    private function excludedHolidays(Carbon $from, Carbon $to): array
    {
        return Holiday::query()
            ->where('excludes_from_leave', true)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->get(['date', 'name'])
            ->groupBy(fn (Holiday $h) => $h->date->toDateString())
            ->map(fn ($group) => $group->pluck('name')->join(' / '))
            ->all();
    }
}
