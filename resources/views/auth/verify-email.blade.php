@extends('layouts.auth-card')
@section('title', 'Verify your email | ResearchHub')
@section('icon', 'mark_email_unread')
@section('heading', 'Check your inbox')
@section('sub')
We sent a verification link to <span class="font-label-md text-label-md text-on-surface">{{ auth()->user()->email }}</span>. Click it to activate your workspace.
@endsection
@section('form')
<div class="space-y-stack-sm">
<form action="{{ route('verification.send') }}" method="POST">
@csrf
<x-auth.submit icon="refresh">Resend Verification Email</x-auth.submit>
</form>
<form action="{{ route('logout') }}" method="POST">
@csrf
<button class="w-full flex items-center justify-center gap-3 py-3 border border-outline-variant rounded-lg font-label-md text-label-md text-on-surface hover:bg-surface-container-low transition-colors" type="submit">
<span class="material-symbols-outlined text-secondary">logout</span>
Sign Out
</button>
</form>
</div>
@endsection
