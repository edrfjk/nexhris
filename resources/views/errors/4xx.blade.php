{{-- Fallback for any 4xx without its own page: 405, 413, 422 and the rest.
     Without it Laravel serves an unbranded Symfony page. --}}
@extends('errors.layout')

@php
    $code = $exception?->getStatusCode() ?? 400;
    $reason = trim($exception?->getMessage() ?? '');

    [$heading, $message] = match ($code) {
        405 => ['That link cannot be opened directly',
            'This address only accepts a submitted form. Go back to the page you were on and use its button instead.'],
        413 => ['That file is too large',
            'The upload is bigger than the server accepts. Reduce the file size and try again.'],
        // The app's own abort(422, '...') messages explain what to do next.
        422 => ['That could not be done',
            $reason ?: 'The record may have changed since the page was opened. Go back, refresh, and try again.'],
        default => ['That request could not be completed',
            'Go back, refresh the page and try again.'],
    };
@endphp

@section('title', $heading)
@section('code', $code)
@section('heading', $heading)

@section('message')
    {{ $message }}
@endsection

@section('actions')
    <a href="{{ url()->previous() }}" class="btn btn-md btn-secondary">Go back</a>
@endsection
