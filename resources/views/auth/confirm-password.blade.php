@extends('layouts.auth-card')
@section('title', 'Confirm password | ResearchHub')
@section('icon', 'shield_lock')
@section('heading', "Confirm it's you")
@section('sub', 'This is a secure area. Please confirm your password before continuing.')
@section('form')
<form action="{{ route('password.confirm') }}" class="space-y-stack-md" method="POST">
@csrf
<x-auth.field autocomplete="current-password" autofocus icon="lock" label="Password" name="password" placeholder="••••••••" type="password"/>
<x-auth.submit>Confirm</x-auth.submit>
</form>
@endsection
