@extends('layouts.app')
@section('title', 'Acknowledgment Compliance')

@section('content')
<x-page-header title="Acknowledgment Compliance" subtitle="{{ $policy->title }}">
    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back
        </a>
    </x-slot:actions>
</x-page-header>

@php
    $acknowledged = $employees->filter(fn ($e) => $e->policyViews->first()?->acknowledged_at)->count();
    $total = $employees->count();
@endphp

<div class="card p-5 mb-6">
    <p class="text-sm text-sand-500 mb-1">Acknowledgment Progress</p>
    <p class="text-2xl font-bold text-sand-800">{{ $acknowledged }} / {{ $total }} employees</p>
    <div class="w-full bg-sand-100 rounded-full h-2 mt-2">
        <div class="h-2 rounded-full bg-forest-500" style="width: {{ $total > 0 ? ($acknowledged / $total) * 100 : 0 }}%"></div>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Viewed</th>
                <th>Acknowledged</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                @php $view = $employee->policyViews->first(); @endphp
                <tr class="border-t border-sand-100">
                    <td>
                        <p class="font-medium text-sand-800">{{ $employee->name }}</p>
                        <p class="text-xs text-sand-400">{{ $employee->employee_number }}</p>
                    </td>
                    <td>{{ $view ? $view->viewed_at->format('M d, Y') : '—' }}</td>
                    <td>
                        @if ($view?->acknowledged_at)
                            <x-badge color="green">Acknowledged {{ $view->acknowledged_at->format('M d, Y') }}</x-badge>
                        @else
                            <x-badge color="gray">Pending</x-badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3"><x-empty-state message="No active employees found." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection