{{-- The terms as their own page, reachable without signing in — a user who
     has not agreed yet has no session, so this cannot sit behind the guard.
     The wording comes from the same partial the sign-in dialog reads. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Terms and Conditions | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-sand-100 py-8 px-5">

<div class="mx-auto w-full max-w-3xl">

    <div class="mb-6 flex items-center gap-4">
        <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC seal" class="h-14 w-14 shrink-0">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-maroon-700">
                Ilocos Sur Polytechnic State College &middot; Tagudin Campus
            </p>
            <h1 class="mt-0.5 text-lg font-bold text-sand-900">NexHRIS &mdash; Terms and Conditions</h1>
            <p class="text-xs text-sand-500">Human Resource Information System</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @include('legal.partials.terms-body')
        </div>
    </div>

    <div class="mt-6 flex items-center justify-between">
        <a href="{{ route('login') }}"
           class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-left />
            Back to sign in
        </a>

        <button type="button" onclick="window.print()" class="btn btn-md btn-ghost">
            <x-heroicon-o-printer />
            Print
        </button>
    </div>

    <p class="mt-6 text-center text-[11px] text-sand-400">
        &copy; {{ date('Y') }} ISPSC Tagudin Campus &middot; Human Resource Management Office
    </p>
</div>

</body>
</html>
