@extends('layouts.auth-card')
@section('title', 'Choose a new password | ResearchHub')
@section('heading', 'Choose a new password')
@section('sub', "Use at least 8 characters. You'll be signed in once it's saved.")
@section('form')
<form action="{{ route('password.store') }}" class="space-y-stack-md" method="POST">
@csrf
<input name="token" type="hidden" value="{{ $request->route('token') }}"/>
<x-auth.field autocomplete="username" icon="mail" label="Email Address" name="email" type="email" :value="$request->email"/>
<x-auth.field autocomplete="new-password" autofocus icon="lock" label="New Password" name="password" placeholder="••••••••" type="password"/>
<x-auth.field autocomplete="new-password" icon="lock_reset" label="Confirm New Password" name="password_confirmation" placeholder="••••••••" type="password"/>
<x-auth.submit icon="check">Save New Password</x-auth.submit>
</form>
@endsection
