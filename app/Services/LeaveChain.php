<?php

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\User;

/**
 * The leave approval chain, derived from who is applying.
 *
 * The sequence is not fixed. Nobody signs their own leave, so a stage is
 * dropped when the applicant is the person who would have reviewed it:
 *
 *   Employee         Dean → HR → Campus Director
 *   Dean             HR → Campus Director            (own stage skipped)
 *   Campus Director  HR                              (HR alone, per the
 *                                                     client: the Campus
 *                                                     Director outranks the
 *                                                     Deans, so no Dean signs
 *                                                     their form, and HR is
 *                                                     final as no role sits
 *                                                     above them)
 *   HR Administrator Dean → Campus Director          (own stage skipped)
 *
 * Skipped stages are still shown to the employee, marked N/A, so the printed
 * trail makes clear why a signature is absent.
 */
class LeaveChain
{
    /** Every stage that can exist, in order. */
    public const STAGES = ['dean', 'hr', 'campus_director'];

    public const LABELS = [
        'dean' => 'Dean',
        'hr' => 'HR Administrator',
        'campus_director' => 'Campus Director',
    ];

    /** The status a form sits at while each stage is pending. */
    public const PENDING_STATUS = [
        'dean' => 'submitted',
        'hr' => 'dean_approved',
        'campus_director' => 'hr_approved',
    ];

    public const RETURNED_STATUS = [
        'dean' => 'dean_returned',
        'hr' => 'hr_returned',
        'campus_director' => 'cd_returned',
    ];

    /** Column prefix each stage records its decision in. */
    public const PREFIX = [
        'dean' => 'dean',
        'hr' => 'hr',
        'campus_director' => 'director',
    ];

    /** Terminal status once every applicable stage has approved. */
    public const APPROVED = 'cd_approved';

    /**
     * The stages this applicant's form must pass, in order.
     *
     * @return array<int, string>
     */
    public function stagesFor(User $applicant): array
    {
        // The Campus Director's own form goes to HR and nobody else. A Dean
        // sits below them, so asking a Dean to sign it would invert the
        // reporting line.
        if ($applicant->isCampusDirector()) {
            return ['hr'];
        }

        $ownStage = $applicant->approvalStage();

        return array_values(array_filter(
            self::STAGES,
            fn (string $stage) => $stage !== $ownStage,
        ));
    }

    /** Stages that do not apply to this applicant, shown as N/A. */
    public function skippedFor(User $applicant): array
    {
        return array_values(array_diff(self::STAGES, $this->stagesFor($applicant)));
    }

    /** The status a newly submitted form should start at. */
    public function initialStatus(User $applicant): string
    {
        $first = $this->stagesFor($applicant)[0] ?? null;

        // An applicant who occupies every reviewing role would have no chain;
        // in practice one person never holds all three, but fail closed.
        return $first ? self::PENDING_STATUS[$first] : self::APPROVED;
    }

    /** The stage currently awaiting a decision, or null if none is. */
    public function currentStage(LeaveApplication $application): ?string
    {
        foreach ($this->stagesFor($application->user) as $stage) {
            if ($application->status === self::PENDING_STATUS[$stage]) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * The status to move to once $stage approves — either the next applicable
     * stage's pending status, or the terminal approved status.
     */
    public function statusAfterApproval(LeaveApplication $application, string $stage): string
    {
        $stages = $this->stagesFor($application->user);
        $position = array_search($stage, $stages, true);

        if ($position === false) {
            return self::APPROVED;
        }

        $next = $stages[$position + 1] ?? null;

        return $next ? self::PENDING_STATUS[$next] : self::APPROVED;
    }

    /** Whether this stage applies to this applicant at all. */
    public function stageApplies(User $applicant, string $stage): bool
    {
        return in_array($stage, $this->stagesFor($applicant), true);
    }

    /** The stage that gives final sign-off for this applicant. */
    public function finalStage(User $applicant): ?string
    {
        $stages = $this->stagesFor($applicant);

        return $stages ? end($stages) : null;
    }

    /**
     * A description of each stage for the employee-facing stepper.
     *
     * @return array<int, array{stage: string, label: string, state: string, who: ?string, at: ?\Illuminate\Support\Carbon, remarks: ?string}>
     */
    public function timeline(LeaveApplication $application): array
    {
        $applicant = $application->user;
        $applicable = $this->stagesFor($applicant);
        $current = $this->currentStage($application);
        $reached = $this->stagesCleared($application);

        $rows = [];

        foreach (self::STAGES as $stage) {
            $prefix = self::PREFIX[$stage];
            $stageStatus = $application->{$prefix . '_status'};

            $state = match (true) {
                ! in_array($stage, $applicable, true) => 'skipped',
                $stageStatus === 'returned' => 'returned',
                in_array($stage, $reached, true) => 'approved',
                $stage === $current => 'current',
                default => 'pending',
            };

            $rows[] = [
                'stage' => $stage,
                'label' => self::LABELS[$stage],
                'state' => $state,
                'who' => $application->{$prefix . '_id'}
                    ? optional(User::find($application->{$prefix . '_id'}))->name
                    : null,
                'at' => $application->{$prefix . '_reviewed_at'},
                'remarks' => $application->{$prefix . '_remarks'},
            ];
        }

        return $rows;
    }

    /** Stages that have already approved this form. */
    private function stagesCleared(LeaveApplication $application): array
    {
        $cleared = [];

        foreach ($this->stagesFor($application->user) as $stage) {
            if ($application->{self::PREFIX[$stage] . '_status'} === 'approved') {
                $cleared[] = $stage;
            }
        }

        return $cleared;
    }
}
