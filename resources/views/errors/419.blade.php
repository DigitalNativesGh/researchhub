@extends('errors.layout')
@section('code', '419')
@section('heading', 'Your session expired')
@section('icon', 'timer_off')
@section('text', 'For your security, the form expired before it was sent. Go back, refresh the page and try again.')
@section('primary')
<button class="bg-primary text-on-primary px-6 py-3 rounded-lg font-label-md text-label-md flex items-center justify-center gap-2 hover:bg-primary-container transition-all" onclick="location.reload()" type="button">
<span class="material-symbols-outlined text-[20px]">refresh</span>Refresh Page
</button>
@endsection
