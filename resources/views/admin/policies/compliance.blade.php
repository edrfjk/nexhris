@extends('layouts.app')
@section('title', 'Acknowledgment Compliance')

@section('content')
<x-page-header title="Acknowledgment Compliance" subtitle="{{ $policy->title }}">
    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </x-slot:actions>
</x-page-header>

@php
    $acknowledged = $employees->filter(fn ($e) => $e->policyViews->first()?->acknowledged_at)->count();
    $total = $employees->count();
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <p class="text-sm text-gray-500 mb-1">Acknowledgment Progress</p>
    <p class="text-2xl font-bold text-gray-800">{{ $acknowledged }} / {{ $total }} employees</p>
    <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
        <div class="h-2 rounded-full bg-green-500" style="width: {{ $total > 0 ? ($acknowledged / $total) * 100 : 0 }}%"></div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3 font-medium">Employee</th>
                <th class="px-5 py-3 font-medium">Viewed</th>
                <th class="px-5 py-3 font-medium">Acknowledged</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                @php $view = $employee->policyViews->first(); @endphp
                <tr class="border-t border-gray-100">
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-800">{{ $employee->name }}</p>
                        <p class="text-xs text-gray-400">{{ $employee->employee_number }}</p>
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $view ? $view->viewed_at->format('M d, Y') : '—' }}</td>
                    <td class="px-5 py-3">
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