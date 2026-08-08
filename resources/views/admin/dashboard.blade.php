@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<x-page-header
    title="{{ now()->format('H') < 12 ? 'Good morning' : (now()->format('H') < 18 ? 'Good afternoon' : 'Good evening') }}, {{ auth()->user()->name ?? 'Admin' }}"
    subtitle="Here's what's happening with employees, leave, and PDS activity for {{ $year }} · {{ now()->format('l, F j, Y') }}"
/>

{{-- KPI cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Total Employees" :value="$totalEmployees" color="maroon" :href="route('admin.employees.index')"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>'
    />
    <x-stat-card label="Pending Leave Applications" :value="$pendingLeave" color="yellow" :href="route('admin.leave.pending')"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
    />
    <x-stat-card label="PDS Submitted / Total" :value="$pdsSubmittedCount . ' / ' . $totalEmployees" color="blue" :href="route('admin.pds.index')"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
    />
    <x-stat-card label="Published Policies" :value="$publishedPolicies" color="green" :href="route('admin.policies.index')"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>'
    />
</div>

{{-- Quick actions --}}
<div class="flex flex-wrap gap-3 mb-6">
    @if (Route::has('admin.employees.create'))
        <a href="{{ route('admin.employees.create') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:border-maroon-300 hover:text-maroon-700 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 7.5v9m4.5-4.5h-9M3 7.5h9m-9 4.5h9m-9 4.5h5.25"/></svg>
            Add Employee
        </a>
    @endif
    <a href="{{ route('admin.leave.pending') }}"
       class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:border-yellow-300 hover:text-yellow-700 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Review Leave Requests
    </a>
    <a href="{{ route('admin.pds.index') }}"
       class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:border-blue-300 hover:text-blue-700 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Review PDS Submissions
    </a>
    @if (Route::has('admin.policies.create'))
        <a href="{{ route('admin.policies.create') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:border-green-300 hover:text-green-700 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Publish Policy
        </a>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- PDS status breakdown --}}
    <x-card title="PDS Status Breakdown ({{ $year }})" class="lg:col-span-1">
        @php
            $breakdown = [
                'not_started' => ['Not Started', 'gray', $pdsNotStartedCount],
                'draft'       => ['Draft', 'blue', $pdsCounts['draft'] ?? 0],
                'submitted'   => ['Submitted', 'yellow', $pdsCounts['submitted'] ?? 0],
                'approved'    => ['Approved', 'green', $pdsCounts['approved'] ?? 0],
                'returned'    => ['Returned', 'red', $pdsCounts['returned'] ?? 0],
            ];
            $barColors = [
                'gray' => 'bg-gray-400', 'blue' => 'bg-blue-500', 'yellow' => 'bg-yellow-500',
                'green' => 'bg-green-500', 'red' => 'bg-red-500',
            ];
        @endphp

        {{-- Single combined stacked bar for an at-a-glance view --}}
        <div class="flex w-full h-2.5 rounded-full overflow-hidden bg-gray-100 mb-4">
            @foreach ($breakdown as [$label, $color, $count])
                @if ($count > 0 && $totalEmployees > 0)
                    <div class="{{ $barColors[$color] }}" style="width: {{ ($count / $totalEmployees) * 100 }}%"></div>
                @endif
            @endforeach
        </div>

        <div class="space-y-3">
            @foreach ($breakdown as [$label, $color, $count])
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ $barColors[$color] }}"></span>
                        <span class="text-gray-600">{{ $label }}</span>
                    </div>
                    <span class="font-semibold text-gray-700">
                        {{ $count }}
                        <span class="text-gray-400 font-normal">
                            ({{ $totalEmployees > 0 ? round(($count / $totalEmployees) * 100) : 0 }}%)
                        </span>
                    </span>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- Recent leave applications --}}
    <x-card title="Recent Leave Applications" class="lg:col-span-1">
        @forelse ($recentApplications as $app)
            <div class="flex items-center justify-between py-2.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }} text-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex-shrink-0 w-8 h-8 rounded-full bg-maroon-100 text-maroon-700 text-xs font-semibold flex items-center justify-center">
                        {{ collect(explode(' ', $app->user->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->join('') }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-medium text-gray-700 truncate">{{ $app->user->name }}</p>
                        <p class="text-xs text-gray-400">{{ $app->leave_type }} · {{ $app->date_from->format('M d') }}</p>
                    </div>
                </div>
                <x-badge :color="match($app->status) { 'approved' => 'green', 'declined' => 'red', default => 'yellow' }">
                    {{ ucfirst($app->status) }}
                </x-badge>
            </div>
        @empty
            <x-empty-state message="No leave applications yet." />
        @endforelse

        @if ($recentApplications->isNotEmpty())
            <a href="{{ route('admin.leave.pending') }}" class="mt-3 inline-block text-sm font-medium text-maroon-600 hover:text-maroon-700">
                View all leave applications &rarr;
            </a>
        @endif
    </x-card>

    {{-- Recent PDS activity --}}
    <x-card title="Recent PDS Activity" class="lg:col-span-1">
        @forelse ($recentPdsActivity as $sub)
            <div class="flex items-center justify-between py-2.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }} text-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold flex items-center justify-center">
                        {{ collect(explode(' ', $sub->user->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->join('') }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-medium text-gray-700 truncate">{{ $sub->user->name }}</p>
                        <p class="text-xs text-gray-400">{{ $sub->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
                <x-badge :color="match($sub->status) { 'approved' => 'green', 'returned' => 'red', default => 'yellow' }">
                    {{ ucfirst($sub->status) }}
                </x-badge>
            </div>
        @empty
            <x-empty-state message="No recent PDS activity." />
        @endforelse

        @if ($recentPdsActivity->isNotEmpty())
            <a href="{{ route('admin.pds.index') }}" class="mt-3 inline-block text-sm font-medium text-maroon-600 hover:text-maroon-700">
                View all PDS submissions &rarr;
            </a>
        @endif
    </x-card>

</div>
@endsection