@extends('errors.layout')

@section('title', 'Not allowed')
@section('code', '403')
@section('heading', 'This is not yours to open')

@section('message')
    Your account does not cover this record. Leave forms, ledger cards and
    Personal Data Sheets are visible only to the person they belong to and the
    reviewers in their approval chain.
@endsection
