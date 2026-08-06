@extends('layouts.app')
@section('title', 'My Dashboard')

@section('content')
<x-page-header title="Welcome, {{ explode(' ', auth()->user()->name)[0] }}" subtitle="Here's a quick overview of your HR records." />

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <x-stat-card label="Vacation Leave Balance" :value="number_format(auth()->user()->leaveBalance->vl_balance ?? 0, 2)" color="green"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
    />
    <x-stat-card label="Sick Leave Balance" :value="number_format(auth()->user()->leaveBalance->sl_balance ?? 0, 2)" color="blue"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
    />
    <x-stat-card label="PDS Status ({{ now()->year }})" :value="ucfirst(str_replace('_', ' ', auth()->user()->pdsSubmissions->where('applicable_year', now()->year)->first()->status ?? 'not started'))" color="maroon"
        icon='<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
    />
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <x-card title="Quick Actions">
        <div class="space-y-2">
            <a href="{{ route('pds.edit') }}" class="block text-sm text-blue-600 hover:underline">Update my Personal Data Sheet →</a>
            <a href="{{ route('leave.index') }}" class="block text-sm text-blue-600 hover:underline">File a leave application →</a>
        </div>
    </x-card>
</div>
@endsection