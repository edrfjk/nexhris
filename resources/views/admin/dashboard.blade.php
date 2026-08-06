@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard" subtitle="Overview of employees, leave, and PDS activity for {{ $year }}." />

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <x-card title="PDS Status Breakdown ({{ $year }})" class="lg:col-span-1">
        <div class="space-y-3">
            @foreach (['not_started' => ['Not Started','gray'], 'draft' => ['Draft','blue'], 'submitted' => ['Submitted','yellow'], 'approved' => ['Approved','green'], 'returned' => ['Returned','red']] as $key => [$label, $color])
                @php $count = $key === 'not_started' ? $pdsNotStartedCount : ($pdsCounts[$key] ?? 0); @endphp
                <div class="flex items-center justify-between text-sm">
                    <x-badge :color="$color">{{ $label }}</x-badge>
                    <span class="font-semibold text-gray-700">{{ $count }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full bg-{{ $color === 'gray' ? 'gray-400' : $color.'-500' }}"
                         style="width: {{ $totalEmployees > 0 ? ($count / $totalEmployees) * 100 : 0 }}%"></div>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card title="Recent Leave Applications" class="lg:col-span-1">
        @forelse ($recentApplications as $app)
            <div class="flex items-center justify-between py-2.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }} text-sm">
                <div>
                    <p class="font-medium text-gray-700">{{ $app->user->name }}</p>
                    <p class="text-xs text-gray-400">{{ $app->leave_type }} · {{ $app->date_from->format('M d') }}</p>
                </div>
                <x-badge :color="match($app->status) { 'approved' => 'green', 'declined' => 'red', default => 'yellow' }">
                    {{ ucfirst($app->status) }}
                </x-badge>
            </div>
        @empty
            <x-empty-state message="No leave applications yet." />
        @endforelse
    </x-card>

    <x-card title="Recent PDS Activity" class="lg:col-span-1">
        @forelse ($recentPdsActivity as $sub)
            <div class="flex items-center justify-between py-2.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }} text-sm">
                <div>
                    <p class="font-medium text-gray-700">{{ $sub->user->name }}</p>
                    <p class="text-xs text-gray-400">{{ $sub->updated_at->diffForHumans() }}</p>
                </div>
                <x-badge :color="match($sub->status) { 'approved' => 'green', 'returned' => 'red', default => 'yellow' }">
                    {{ ucfirst($sub->status) }}
                </x-badge>
            </div>
        @empty
            <x-empty-state message="No recent PDS activity." />
        @endforelse
    </x-card>

</div>
@endsection