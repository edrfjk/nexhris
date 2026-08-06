@extends('layouts.app')
@section('title', "Digital ID — {$employee->name}")

@section('content')
<x-page-header title="Digital Employee ID" subtitle="{{ $employee->name }}">
    <x-slot:actions>
        <a href="{{ route('admin.employees.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<x-id-card :employee="$employee" :upload-route="route('admin.employees.id.photo', $employee)" />
@endsection