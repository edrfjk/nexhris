@extends('layouts.app')
@section('title', 'Personal Data Sheet — ' . $steps[$step]['label'])

@section('content')
<div class="mb-6">
    <div class="flex items-center justify-between text-xs mb-2">
        <span class="font-semibold text-maroon-800">Step {{ $step }} of {{ $totalSteps }}: {{ $steps[$step]['label'] }}</span>
        <span class="text-gray-500">
            PDS Status ({{ $submission->applicable_year }}):
            <span class="font-semibold capitalize">{{ str_replace('_', ' ', $submission->status) }}</span>
        </span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-2">
        <div class="bg-maroon-800 h-2 rounded-full" style="width: {{ ($step / $totalSteps) * 100 }}%"></div>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2 text-sm">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded bg-red-100 text-red-800 px-4 py-2 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white rounded shadow p-6">
    @include('employee.pds.steps.' . $stepKey)
</div>

<div class="flex justify-between mt-4">
    @if ($step > 1)
        <a href="{{ route('pds.step', ['step' => $step - 1]) }}" class="px-4 py-2 rounded border text-sm">← Back</a>
    @else
        <span></span>
    @endif

    @if ($step < $totalSteps)
        <a href="{{ route('pds.step', ['step' => $step + 1]) }}" class="px-4 py-2 rounded bg-maroon-800 text-white text-sm hover:bg-maroon-900">
            @if (in_array($stepKey, ['education', 'eligibility', 'work', 'voluntary', 'training', 'references']))
                Continue →
            @else
                Skip / Next →
            @endif
        </a>
    @endif
</div>
@endsection