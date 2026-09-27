@extends('errors.layout')

@php
    // abort(403, '...') calls across the app say exactly why, e.g. "Only HR
    // can post leave credits." Show that; fall back to the general wording
    // for the framework's stock messages.
    $reason = trim($exception?->getMessage() ?? '');
    $generic = ['', 'Forbidden', 'This action is unauthorized.', 'Unauthorized access.',
        'You do not have permission to access this page.'];
@endphp

@section('title', 'Not allowed')
@section('code', '403')
@section('heading', 'This is not yours to open')

@section('message')
    @if (! in_array($reason, $generic, true))
        {{ $reason }}
    @else
        Your account does not cover this page or record. Leave forms, ledger
        cards and Personal Data Sheets are visible only to the person they belong
        to and the reviewers in their approval chain.
    @endif
@endsection
