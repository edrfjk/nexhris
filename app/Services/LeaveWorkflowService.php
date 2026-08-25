<?php

namespace App\Services;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\User;
use App\Notifications\LeaveStageChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Drives the leave approval chain.
 *
 * The sequence itself lives in LeaveChain, which derives it from the
 * applicant's role so nobody ever signs their own leave. This service applies
 * decisions, records them, and notifies whoever is next.
 */
class LeaveWorkflowService
{
    public function __construct(
        private ActivityLogger $log,
        private LeaveChain $chain,
    ) {
    }

    public function chain(): LeaveChain
    {
        return $this->chain;
    }

    /**
     * Can $reviewer act on $application right now? A Dean is additionally
     * bounded by college, enforced on the foreign key.
     */
    public function canReview(User $reviewer, LeaveApplication $application): bool
    {
        $stage = $reviewer->approvalStage();

        if ($stage === null || $this->chain->currentStage($application) !== $stage) {
            return false;
        }

        // Nobody reviews their own application, even where the chain would
        // otherwise allow it.
        if ($application->user_id === $reviewer->id) {
            return false;
        }

        if ($stage === 'dean') {
            return $this->deanCoversEmployee($reviewer, $application->user);
        }

        return true;
    }

    /**
     * The Dean's data boundary, on the real foreign key. A Dean with no
     * college covers nobody — better an empty queue than another college's
     * forms.
     */
    public function deanCoversEmployee(User $dean, ?User $employee): bool
    {
        if (! $employee || ! $dean->college_id) {
            return false;
        }

        return $dean->college_id === $employee->college_id;
    }

    // ------------------------------------------------------------------
    // Submission
    // ------------------------------------------------------------------

    /** Places a freshly uploaded form at the first stage that applies. */
    public function submit(LeaveApplication $application): void
    {
        $application->update(['status' => $this->chain->initialStatus($application->user)]);

        $this->log->log(
            'leave.submitted',
            "{$application->user->name} submitted a leave form.",
            $application,
            ['skipped' => $this->chain->skippedFor($application->user)],
            $application->user,
        );

        $this->notifyCurrentReviewer($application);
    }

    // ------------------------------------------------------------------
    // Decisions
    // ------------------------------------------------------------------

    public function approve(User $reviewer, LeaveApplication $application, ?string $remarks = null): string
    {
        $stage = $reviewer->approvalStage();

        abort_unless($stage !== null && $this->canReview($reviewer, $application), 403,
            'This leave form is not waiting on your review.');

        $nextStatus = $this->chain->statusAfterApproval($application, $stage);
        $prefix = LeaveChain::PREFIX[$stage];

        DB::transaction(function () use ($application, $reviewer, $stage, $prefix, $nextStatus, $remarks) {
            $application->update([
                'status' => $nextStatus,
                $prefix . '_status' => 'approved',
                $prefix . '_id' => $reviewer->id,
                $prefix . '_reviewed_at' => now(),
                $prefix . '_remarks' => $remarks,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            LeaveApproval::create([
                'leave_application_id' => $application->id,
                'stage' => $stage,
                'user_id' => $reviewer->id,
                'action' => 'approved',
                'remarks' => $remarks,
            ]);
        });

        $application->refresh();

        $this->log->log(
            'leave.approved',
            "{$reviewer->name} (" . LeaveChain::LABELS[$stage] . ") approved leave for {$application->user->name}.",
            $application,
            ['stage' => $stage, 'remarks' => $remarks],
            $reviewer,
        );

        if ($application->status === LeaveChain::APPROVED) {
            $this->notifyFullyApproved($application);

            return 'Fully approved. The employee can print the form, and HR can post it to the ledger.';
        }

        $this->notifyCurrentReviewer($application);

        $next = $this->chain->currentStage($application);

        return 'Approved and forwarded to the ' . LeaveChain::LABELS[$next] . '.';
    }

    public function returnForRevision(User $reviewer, LeaveApplication $application, string $remarks): string
    {
        $stage = $reviewer->approvalStage();

        abort_unless($stage !== null && $this->canReview($reviewer, $application), 403,
            'This leave form is not waiting on your review.');

        $prefix = LeaveChain::PREFIX[$stage];

        DB::transaction(function () use ($application, $reviewer, $stage, $prefix, $remarks) {
            $application->update([
                'status' => LeaveChain::RETURNED_STATUS[$stage],
                $prefix . '_status' => 'returned',
                $prefix . '_id' => $reviewer->id,
                $prefix . '_reviewed_at' => now(),
                $prefix . '_remarks' => $remarks,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'remarks' => $remarks,
            ]);

            LeaveApproval::create([
                'leave_application_id' => $application->id,
                'stage' => $stage,
                'user_id' => $reviewer->id,
                'action' => 'returned',
                'remarks' => $remarks,
            ]);
        });

        $this->log->log(
            'leave.returned',
            "{$reviewer->name} (" . LeaveChain::LABELS[$stage] . ") returned leave for {$application->user->name}.",
            $application,
            ['stage' => $stage, 'remarks' => $remarks],
            $reviewer,
        );

        $this->notifyEmployee(
            $application,
            'Your leave form was returned',
            LeaveChain::LABELS[$stage] . " returned your leave form: \"{$remarks}\"",
            'Correct and re-upload',
            route('leave.index'),
            'error',
        );

        return 'Returned to the employee with your remarks.';
    }

    // ------------------------------------------------------------------
    // Queues
    // ------------------------------------------------------------------

    /** Forms this reviewer is currently responsible for. */
    public function queueFor(User $reviewer)
    {
        $stage = $reviewer->approvalStage();

        if ($stage === null) {
            return LeaveApplication::query()->whereRaw('1 = 0');
        }

        $query = LeaveApplication::with('user')
            ->where('status', LeaveChain::PENDING_STATUS[$stage])
            // Never surface a reviewer's own application to themselves.
            ->where('user_id', '!=', $reviewer->id);

        // Server-side college scoping for the Dean, in the query itself.
        if ($stage === 'dean') {
            $query->whereHas('user', fn ($q) => $q->where('college_id', $reviewer->college_id));
        }

        return $query;
    }

    /**
     * Re-submitting a returned form clears every stage and restarts the chain
     * from whichever stage applies first.
     */
    public function resetForResubmission(LeaveApplication $application): void
    {
        $application->update([
            'status' => $this->chain->initialStatus($application->user),
            'remarks' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'dean_status' => 'pending', 'dean_id' => null, 'dean_reviewed_at' => null, 'dean_remarks' => null,
            'hr_status' => 'pending', 'hr_id' => null, 'hr_reviewed_at' => null, 'hr_remarks' => null,
            'director_status' => 'pending', 'director_id' => null, 'director_reviewed_at' => null, 'director_remarks' => null,
        ]);

        $this->log->log(
            'leave.resubmitted',
            "{$application->user->name} re-uploaded a corrected leave form.",
            $application,
            actor: $application->user,
        );

        $this->notifyCurrentReviewer($application->refresh());
    }

    // ------------------------------------------------------------------
    // Notification routing
    // ------------------------------------------------------------------

    /**
     * Notifies whoever the form now sits with. The Dean is resolved from the
     * employee's college, so a form always reaches the right one.
     */
    private function notifyCurrentReviewer(LeaveApplication $application): void
    {
        $stage = $this->chain->currentStage($application);

        if (! $stage) {
            return;
        }

        $recipients = $this->reviewersFor($stage, $application);

        if ($recipients->isEmpty()) {
            // Worth recording: a form with no reachable reviewer will sit
            // untouched until someone notices.
            $this->log->log(
                'leave.no_reviewer',
                'No ' . LeaveChain::LABELS[$stage] . " is assigned to review {$application->user->name}'s leave form.",
                $application,
                ['stage' => $stage],
            );

            return;
        }

        Notification::send($recipients, new LeaveStageChanged(
            $application,
            'A leave form needs your review',
            "{$application->user->name} has submitted a leave form awaiting your approval as "
                . LeaveChain::LABELS[$stage] . '.',
            'Review the form',
            route('admin.leave.review.show', $application),
            'info',
        ));
    }

    private function notifyFullyApproved(LeaveApplication $application): void
    {
        $this->notifyEmployee(
            $application,
            'Your leave form is fully approved',
            'Every reviewer has signed off. You can now print the approval sheet and collect the wet signatures.',
            'Print the approval sheet',
            route('leave.index'),
            'success',
        );

        // HR still owes the ledger a posting.
        $hr = User::where('role', 'admin')->where('status', 'active')->get();

        if ($hr->isNotEmpty()) {
            Notification::send($hr, new LeaveStageChanged(
                $application,
                'Approved leave awaiting a ledger posting',
                "{$application->user->name}'s leave is fully approved. Record the days and credits used on their ledger card.",
                'Post to the ledger',
                route('admin.leave.review.show', $application),
                'warning',
            ));
        }
    }

    private function notifyEmployee(
        LeaveApplication $application,
        string $headline,
        string $detail,
        string $actionLabel,
        string $actionUrl,
        string $tone,
    ): void {
        $application->user?->notify(new LeaveStageChanged(
            $application, $headline, $detail, $actionLabel, $actionUrl, $tone,
        ));
    }

    /** Everyone who may act at $stage for this particular form. */
    private function reviewersFor(string $stage, LeaveApplication $application)
    {
        $applicantId = $application->user_id;

        if ($stage === 'dean') {
            // The Dean of record for the employee's college; fall back to any
            // Dean sitting in that college if none is nominated.
            $college = College::find($application->user->college_id);

            $dean = $college?->dean;

            $candidates = $dean
                ? collect([$dean])
                : User::where('role', 'dean')
                    ->where('college_id', $application->user->college_id)
                    ->get();
        } else {
            $role = $stage === 'hr' ? 'admin' : 'campus_director';
            $candidates = User::where('role', $role)->get();
        }

        return $candidates
            ->where('status', 'active')
            ->reject(fn (User $u) => $u->id === $applicantId)
            ->values();
    }
}
