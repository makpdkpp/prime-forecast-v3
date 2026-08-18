@extends('adminlte::page')
@section('title', 'โปรไฟล์และความปลอดภัย | PrimeForecast')
@section('content_header')@stop
@section('content')
    @include('shared.profile-v3', ['routePrefix' => 'user'])
    @include('user.partials.mobile-nav')
@stop
@section('css')<link rel="stylesheet" href="{{ asset('css/sales-v3.css') }}">@stop
@section('js')<script src="{{ asset('js/profile-v3.js') }}"></script>@stop
