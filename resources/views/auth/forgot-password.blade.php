@extends('layouts.auth-card')
@section('title', 'Reset your password | ResearchHub')
@section('heading', 'Forgot your password?')
@section('sub', "Enter your account email and we'll send you a link to choose a new one.")
@section('form')
<form action="{{ route('password.email') }}" class="space-y-stack-md" method="POST">
@csrf
<x-auth.field autocomplete="email" autofocus icon="mail" label="Email Address" name="email" placeholder="e.g. researcher@university.edu" type="email"/>
<x-auth.submit icon="send">Email Reset Link</x-auth.submit>
</form>
@endsection
@section('below')
<p class="text-center mt-stack-md font-body-sm text-body-sm text-on-surface-variant">
Remembered it? <a class="text-primary font-label-md text-label-md hover:underline underline-offset-4" href="{{ route('login') }}">Back to sign in</a>
</p>
@endsection
