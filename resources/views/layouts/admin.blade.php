@extends('layouts.base')

@php
    $nav = trim($__env->yieldContent('nav', 'dashboard'));
    $user = auth()->user();
    $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
    $queueCount = \App\Models\Project::whereIn('workflow_status', array_map(fn ($s) => $s->value, array_filter(\App\Enums\WorkflowStatus::cases(), fn ($s) => $s->needsAdmin())))->count();
    $navItems = [
        ['dashboard', 'space_dashboard', 'Overview', route('admin.dashboard'), null],
        ['queue', 'inbox', 'Work Queue', route('admin.queue'), $queueCount ?: null],
        ['users', 'group', 'Users', route('admin.users.index'), null],
        ['projects', 'folder_shared', 'Projects', route('admin.projects.index'), null],
        ['payments', 'payments', 'Payments', route('admin.payments.index'), null],
        ['usage', 'data_usage', 'AI Usage & Cost', route('admin.usage'), null],
        ['jobs', 'error_med', 'Failed Jobs', route('admin.jobs.index'), $failedJobs ?: null],
        ['activity', 'history', 'Activity Log', route('admin.activity'), null],
        ['settings', 'tune', 'Platform Settings', route('admin.settings'), null],
        ['notifications', 'notifications', 'Notifications', route('notifications.index'), $user->unreadNotifications()->count() ?: null],
    ];
@endphp

@section('title')@yield('page_title', 'Admin') | ResearchHub Admin @endsection

@section('body')
<body class="bg-surface-bright text-on-surface">
<aside class="h-screen w-64 fixed left-0 top-0 bg-primary text-on-primary flex flex-col p-4 z-50">
<div class="mb-8 px-2 flex items-center gap-3">
<div class="w-8 h-8 bg-on-primary rounded flex items-center justify-center text-primary">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">admin_panel_settings</span>
</div>
<div>
<h1 class="font-headline-md text-headline-md font-bold">ResearchHub</h1>
<p class="text-[10px] uppercase tracking-widest text-on-primary/70 font-bold">Administration</p>
</div>
</div>
<nav class="flex-1 space-y-1">
@foreach ($navItems as [$key, $icon, $label, $href, $count])
<a class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ $nav === $key ? 'bg-on-primary/15 font-bold' : 'text-on-primary/80 hover:bg-on-primary/10' }}" href="{{ $href }}">
<span class="material-symbols-outlined" @if ($nav === $key) style="font-variation-settings: 'FILL' 1;" @endif>{{ $icon }}</span>
<span class="font-label-md text-label-md flex-1">{{ $label }}</span>
@if ($count)
<span class="px-2 py-0.5 rounded-full bg-error text-on-error text-[11px] font-bold">{{ $count }}</span>
@endif
</a>
@endforeach
@can('viewHorizon')
<a class="flex items-center gap-3 px-4 py-3 rounded-lg text-on-primary/80 hover:bg-on-primary/10 transition-colors" href="{{ url(config('horizon.path', 'horizon')) }}" target="_blank" rel="noopener">
<span class="material-symbols-outlined">monitor_heart</span>
<span class="font-label-md text-label-md flex-1">Queues (Horizon)</span>
<span class="material-symbols-outlined text-[16px]">open_in_new</span>
</a>
@endcan
</nav>
<div class="mt-auto pt-4 border-t border-on-primary/20 space-y-2">
<a class="flex items-center gap-3 px-4 py-2 rounded-lg text-on-primary/80 hover:bg-on-primary/10 transition-colors font-label-md text-label-md" href="{{ route('dashboard') }}">
<span class="material-symbols-outlined text-[20px]">arrow_back</span>
Back to ResearchHub
</a>
<div class="flex items-center gap-3 px-4 py-2 bg-on-primary/10 rounded-lg">
<x-avatar :user="$user" class="w-8 h-8 text-[10px]"/>
<div class="overflow-hidden">
<p class="font-label-md text-label-md truncate">{{ $user->name }}</p>
<p class="text-[10px] text-on-primary/70 truncate">Administrator</p>
</div>
</div>
<form action="{{ route('logout') }}" method="POST">
@csrf
<button class="w-full flex items-center gap-3 px-4 py-2 text-on-primary/80 hover:bg-on-primary/10 transition-colors rounded-lg font-label-md text-label-md" type="submit">
<span class="material-symbols-outlined text-[20px]">logout</span>
Sign Out
</button>
</form>
</div>
</aside>
<main class="ml-64 p-margin-desktop min-h-screen">
<div class="max-w-container-max mx-auto space-y-stack-md">
@include('partials.flash')
@yield('content')
</div>
</main>
@stack('scripts')
</body>
@endsection
