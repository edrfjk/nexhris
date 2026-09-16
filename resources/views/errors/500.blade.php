@extends('errors.layout')

@section('title', 'Something went wrong')
@section('code', '500')
@section('heading', 'Something went wrong at our end')

@section('message')
    This one is not your doing. The fault has been written to the system log
    with enough detail to trace it. Nothing you had already saved is affected.
@endsection
