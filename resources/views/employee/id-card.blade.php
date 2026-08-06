@extends('layouts.app')
@section('title', 'My Digital ID')

@section('content')
<x-page-header title="My Digital ID" subtitle="View or update your ID photo." />

<x-id-card :employee="$employee" :upload-route="route('my-id.photo.update')" />
@endsection