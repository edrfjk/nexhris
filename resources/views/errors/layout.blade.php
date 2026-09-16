{{-- Shared shell for the error pages.

     Deliberately standalone rather than extending the app layout: the app
     layout reads the signed-in user to build the sidebar, and the pages that
     most need to render are exactly the ones where that may not hold — an
     expired session, a failed authorisation, a request that never reached a
     controller. An error page that itself errors is no error page at all. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Something went wrong') | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-sand-50">

<div class="min-h-screen flex flex-col items-center justify-center px-6 py-16">

    <div class="w-full max-w-lg">

        <div class="flex items-center gap-4 mb-8">
            <img src="{{ asset('images/ispsc-logo.png') }}" alt=""
                 class="w-12 h-12 object-contain shrink-0">
            <div class="leading-tight">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-maroon-800">
                    NexHRIS
                </p>
                <p class="text-[11px] text-sand-500">ISPSC Tagudin Campus</p>
            </div>
        </div>

        <div class="card p-8">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sand-400">
                Error @yield('code')
            </p>

            <h1 class="mt-2 text-2xl font-semibold text-sand-900 leading-tight">
                @yield('heading')
            </h1>

            <p class="mt-3 text-sand-600 leading-relaxed">
                @yield('message')
            </p>

            <div class="mt-7 flex flex-wrap gap-2.5">
                @auth
                    <a href="{{ auth()->user()->isReviewer() ? route('admin.dashboard') : route('employee.dashboard') }}"
                       class="btn btn-md btn-primary">
                        Back to the dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-md btn-primary">
                        Sign in
                    </a>
                @endauth

                @yield('actions')
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-sand-400">
            If this keeps happening, please tell the HR Office what you were doing.
        </p>
    </div>
</div>

</body>
</html>
