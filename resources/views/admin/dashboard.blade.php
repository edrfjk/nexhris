@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('content')
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded shadow p-6">
        <p class="text-sm text-gray-500">Total Employees</p>
        <p class="text-2xl font-bold">{{ \App\Models\User::where('role','employee')->count() }}</p>
    </div>
</div>
@endsection