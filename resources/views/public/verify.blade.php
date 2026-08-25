<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Verification | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center p-5">

<div class="w-full max-w-sm">
    <div class="text-center mb-6">
        <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal" class="w-16 h-16 mx-auto mb-3">
        <h1 class="text-lg font-bold text-maroon-800">NexHRIS</h1>
        <p class="text-xs text-sand-500">Ilocos Sur Polytechnic State College · Tagudin Campus</p>
    </div>

    <div class="card overflow-hidden">
        @if ($employee)
            <div class="px-5 py-4 {{ $employee->status === 'active' ? 'bg-forest-700' : 'bg-sand-500' }} text-white">
                <div class="flex items-center gap-2">
                    @if ($employee->status === 'active')
                        <x-heroicon-o-check-badge class="w-5 h-5" />
                        <span class="font-semibold">Verified — Active employee</span>
                    @else
                        <x-heroicon-o-x-circle class="w-5 h-5" />
                        <span class="font-semibold">Not an active employee</span>
                    @endif
                </div>
            </div>

            <div class="card-body">
                <div class="flex items-center gap-4 mb-5">
                    @if ($employee->profile_photo_path)
                        <img src="{{ Storage::url($employee->profile_photo_path) }}" alt=""
                             class="w-16 h-16 rounded-full object-cover border border-sand-200">
                    @else
                        <div class="w-16 h-16 rounded-full bg-maroon-800 text-white flex items-center
                                    justify-center text-xl font-semibold">
                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                        </div>
                    @endif

                    <div class="min-w-0">
                        <p class="font-semibold text-sand-900">{{ $employee->name }}</p>
                        <p class="text-[13px] text-sand-600">{{ $employee->position ?: '—' }}</p>
                    </div>
                </div>

                <dl class="space-y-2.5 text-[13px] border-t border-sand-200 pt-4">
                    <div class="flex justify-between gap-3">
                        <dt class="text-sand-500">College / Office</dt>
                        <dd class="text-sand-800 text-right">{{ $employee->college->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-sand-500">Status</dt>
                        <dd class="text-right">
                            <span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-slate' }}">
                                {{ ucfirst($employee->status) }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="card-footer">
                <p class="text-[11px] text-sand-500 text-center">
                    Verified {{ now()->format('F j, Y \a\t g:i A') }}.
                    This page confirms employment only.
                </p>
            </div>
        @else
            <div class="card-body text-center py-10">
                <x-heroicon-o-x-circle class="w-10 h-10 text-sand-300 mx-auto mb-3" />
                <p class="font-semibold text-sand-800">Could not verify this ID</p>
                <p class="text-[13px] text-sand-500 mt-1">
                    This code does not match any current employee record.
                    Contact the HR Office if you believe this is a mistake.
                </p>
            </div>
        @endif
    </div>

    <p class="text-center text-[11px] text-sand-400 mt-5">
        &copy; {{ date('Y') }} ISPSC Tagudin Campus
    </p>
</div>

</body>
</html>
