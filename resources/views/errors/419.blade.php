@extends('errors.layout')

@section('title', 'Session expired')
@section('code', '419')
@section('heading', 'You were signed out while the page was open')

@section('message')
    Sessions end after a period of inactivity, so a form left open too long
    cannot be submitted. Sign in again and your work is still where you left it.
@endsection
