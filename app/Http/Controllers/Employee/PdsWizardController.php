<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsSubmission;
use App\Services\PdsWizardSteps;
use Illuminate\Support\Facades\Auth;

class PdsWizardController extends Controller
{
    public function show(int $step)
    {
        if ($step < 1 || $step > PdsWizardSteps::total()) {
            abort(404);
        }

        $user = Auth::user()->load([
            'pdsPersonalInformation', 'pdsFamilyBackground', 'pdsChildren',
            'pdsEducationalBackgrounds', 'pdsCivilServiceEligibilities',
            'pdsWorkExperiences', 'pdsVoluntaryWorks', 'pdsTrainings',
            'pdsOtherInformation', 'pdsQuestionnaire', 'pdsReferences', 'pdsDeclaration',
        ]);

        $submission = PdsSubmission::firstOrCreate(
            ['user_id' => $user->id, 'applicable_year' => now()->year],
            ['status' => 'not_started']
        );

        $stepKey = PdsWizardSteps::key($step);
        $totalSteps = PdsWizardSteps::total();

        return view('employee.pds.wizard', [
            'user' => $user,
            'submission' => $submission,
            'step' => $step,
            'stepKey' => $stepKey,
            'totalSteps' => $totalSteps,
            'steps' => PdsWizardSteps::STEPS,
        ]);
    }
}