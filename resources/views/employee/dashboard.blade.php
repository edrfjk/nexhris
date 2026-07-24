@extends('layouts.app')
@section('title', 'My Dashboard')
@section('content')
<p>Welcome, {{ auth()->user()->name }}.</p>
@endsection