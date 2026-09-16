@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('heading', 'That page is not here')

@section('message')
    The link may be out of date, or the record it pointed at may have been
    removed. Nothing has gone wrong with your account.
@endsection
