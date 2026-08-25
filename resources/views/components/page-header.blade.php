@props(['title', 'subtitle' => null, 'back' => null, 'crumbs' => [], 'section' => null])

@php
    /**
     * The application bar carries the module you are in — never the page
     * title, which lives once in the content below. Repeating the title in
     * both places is what produced the doubled heading.
     *
     * The module is derived from the route, so individual views do not have
     * to declare it; pass `section` to override.
     */
    $moduleFor = function (): string {
        $route = request()->route()?->getName() ?? '';

        return match (true) {
            str_starts_with($route, 'admin.leave.review') => 'Leave Approvals',
            str_starts_with($route, 'admin.leave.templates'),
            str_starts_with($route, 'admin.pds.templates') => 'Templates',
            str_starts_with($route, 'admin.leave') => 'Leave Management',
            str_starts_with($route, 'admin.employees') => 'Employee Accounts',
            str_starts_with($route, 'admin.colleges') => 'Colleges & Offices',
            str_starts_with($route, 'admin.pds') => 'PDS Review',
            str_starts_with($route, 'admin.policies') => 'HR Policies',
            str_starts_with($route, 'admin.announcements') => 'Announcements',
            str_starts_with($route, 'admin.activity-logs') => 'Audit Trail',
            str_starts_with($route, 'admin.dashboard') => 'Overview',
            str_starts_with($route, 'leave') => 'My Leave',
            str_starts_with($route, 'pds') => 'My Personal Data Sheet',
            str_starts_with($route, 'policies') => 'HR Policies',
            str_starts_with($route, 'announcements') => 'Announcements',
            str_starts_with($route, 'notifications') => 'Notifications',
            str_starts_with($route, 'my-id') => 'My Digital ID',
            str_starts_with($route, 'profile') => 'My Profile',
            str_starts_with($route, 'employee.dashboard') => 'Overview',
            default => auth()->user()?->isEmployee() ? 'My Records' : 'Administration',
        };
    };
@endphp

@php
    $module = $section ?? $moduleFor();

    // On an index page the module name and the page title are the same words,
    // and printing both is the duplicate heading users kept seeing. The
    // sidebar already marks the active module, so the bar simply stays empty
    // there and earns its space only on detail pages.
    $showModule = mb_strtolower(trim($module)) !== mb_strtolower(trim($title));
@endphp

@push('page-title')
    @if ($showModule)
        <p class="text-[13px] font-medium text-sand-700 truncate">{{ $module }}</p>
    @endif
@endpush

<div class="mb-5">
    {{-- Breadcrumbs sit above the title, in the content, where a trail belongs. --}}
    @if ($crumbs)
        <nav class="crumbs mb-2" aria-label="Breadcrumb">
            @foreach ($crumbs as $label => $url)
                @if ($url)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span>{{ $label }}</span>
                @endif
                <x-heroicon-o-chevron-right />
            @endforeach
            <span class="font-medium text-sand-700 truncate">{{ $title }}</span>
        </nav>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="page-title">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-[13px] text-sand-500 mt-1">{{ $subtitle }}</p>
            @endif
        </div>

        @if (isset($actions) || $back)
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if ($back)
                    <a href="{{ $back }}" class="btn btn-sm btn-secondary">
                        <x-heroicon-o-arrow-left />
                        Back
                    </a>
                @endif
                {{ $actions ?? '' }}
            </div>
        @endif
    </div>
</div>
