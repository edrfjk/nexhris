<?php

/**
 * One contender in a race. Boots NexHRIS against the integration database,
 * waits for the shared start time, performs one action and prints the result
 * as JSON on its last line.
 *
 * `hold` makes the process keep its transaction open for that many seconds
 * after its first write, and `delay` makes it start that much later, so the
 * second contender is guaranteed to arrive while the first still holds the
 * row — true overlap, not a lucky ordering.
 */

use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Services\LeaveAlreadyDecided;
use App\Services\LeaveWorkflowService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../../vendor/autoload.php';

$job = json_decode($argv[1], true);

$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (DB::connection()->getDatabaseName() !== 'nexhris_integration_test') {
    fwrite(STDERR, 'Refusing to run outside nexhris_integration_test.');
    exit(1);
}

$hold = (float) ($job['hold'] ?? 0);

if ($hold > 0) {
    $sleep = function () use ($hold) {
        usleep((int) ($hold * 1_000_000));
    };
    LeaveApproval::created($sleep);
    LeaveLedgerEntry::created($sleep);
    LeaveApplication::created($sleep);
}

while (microtime(true) < $job['start_at'] + (float) ($job['delay'] ?? 0)) {
    usleep(1000);
}

$reviewer = User::findOrFail($job['reviewer']);
auth()->setUser($reviewer);
$application = isset($job['application']) ? LeaveApplication::findOrFail($job['application']) : null;
$started = microtime(true);

try {
    if ($job['action'] === 'approve') {
        $message = app(LeaveWorkflowService::class)->approve($reviewer, $application);
    } elseif ($job['action'] === 'return') {
        $message = app(LeaveWorkflowService::class)->returnForRevision($reviewer, $application, 'Returned in race');
    } elseif (in_array($job['action'], ['file', 'file_online'], true)) {
        // $job['reviewer'] is the employee filing here.
        // A private folder per worker. Storage::fake() would empty the shared
        // storage/framework/testing/disks/local, which the SQLite suite uses.
        config(['filesystems.disks.local.root' => sys_get_temp_dir() . '/nexhris-race-' . getmypid()]);
        Illuminate\Support\Facades\Storage::forgetDisk('local');
        Illuminate\Support\Facades\Notification::fake();
        $online = $job['action'] === 'file_online';
        $input = $job['input'] + ($online ? ['details' => ['location' => 'within'], 'commutation' => 'not_requested'] : []);
        $files = $online ? [] : ['leave_form' => Illuminate\Http\UploadedFile::fake()->create('leave.pdf', 20, 'application/pdf')];
        $request = Illuminate\Http\Request::create('/', 'POST', $input, [], $files);
        $request->setUserResolver(fn () => $reviewer);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);
        $controller = app(App\Http\Controllers\Employee\LeaveApplicationController::class);
        $online
            ? $controller->storeOnline($request, app(App\Services\LeaveForm\LeavePolicy::class), app(LeaveWorkflowService::class))
            : $controller->store($request, app(LeaveWorkflowService::class), app(App\Services\LeaveForm\LeavePolicy::class));
        $message = 'filed';
    } else {
        $request = Illuminate\Http\Request::create('/', 'POST', ($job['input'] ?? []) + [
            'ledger' => 'leave', 'period_from' => '2026-10-19', 'period_to' => '2026-10-20',
            'days' => 2, 'vl_used' => 2, 'remarks' => 'VL',
        ]);
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $reviewer);
        app()->instance('request', $request);
        $response = app(App\Http\Controllers\Admin\LeaveReviewController::class)
            ->postToLedger($request, $application, app(App\Services\LeaveLedgerService::class));
        $message = session('error') ?: 'posted';
    }
    $outcome = 'ok';
} catch (LeaveAlreadyDecided $e) {
    [$outcome, $message] = ['already_decided', $e->getMessage()];
} catch (Illuminate\Validation\ValidationException $e) {
    [$outcome, $message] = ['refused', json_encode($e->errors())];
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
    [$outcome, $message] = ['http_' . $e->getStatusCode(), $e->getMessage()];
} catch (Throwable $e) {
    [$outcome, $message] = ['error', get_class($e) . ': ' . $e->getMessage()];
}

echo "\n" . json_encode([
    'outcome' => $outcome,
    'message' => $message,
    'waited' => round(microtime(true) - $started, 2),
]) . "\n";
