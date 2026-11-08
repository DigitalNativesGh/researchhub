@extends('layouts.base')

@php
    $nav = trim($__env->yieldContent('nav', 'projects'));
    $user = auth()->user();
    $navItems = [
        ['dashboard', 'dashboard', 'Dashboard', route('dashboard')],
        ['projects', 'folder_shared', 'My Projects', route('projects.index')],
        ['profile', 'person', 'Profile', route('profile.edit')],
    ];
@endphp

@section('body')
<body class="@yield('body_class', 'bg-surface text-on-surface')" data-push="{{ auth()->user()?->push_notifications ? 1 : 0 }}">
<!-- SideNavBar Shell -->
<aside class="h-screen w-64 fixed left-0 top-0 border-r border-outline-variant bg-surface flex flex-col p-4 z-50">
<div class="mb-8 px-2 flex items-center gap-3">
<div class="w-8 h-8 bg-primary rounded flex items-center justify-center text-on-primary">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">school</span>
</div>
<div>
<h1 class="font-headline-md text-headline-md font-bold text-primary">ResearchHub</h1>
<p class="text-[10px] uppercase tracking-widest text-on-surface-variant font-bold">Academic Excellence</p>
</div>
</div>
<nav class="flex-1 space-y-2">
@foreach ($navItems as [$key, $icon, $label, $href])
@if ($nav === $key)
<a class="flex items-center gap-3 px-4 py-3 bg-secondary-container text-on-secondary-container rounded-lg font-bold transition-all duration-200 ease-in-out" href="{{ $href }}">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">{{ $icon }}</span>
<span class="font-label-md text-label-md">{{ $label }}</span>
</a>
@else
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:bg-surface-container-high transition-colors rounded-lg group" href="{{ $href }}">
<span class="material-symbols-outlined">{{ $icon }}</span>
<span class="font-label-md text-label-md">{{ $label }}</span>
</a>
@endif
@endforeach
@if ($user->is_admin)
<a class="flex items-center gap-3 px-4 py-3 mt-4 border border-outline-variant text-primary hover:bg-surface-container-high transition-colors rounded-lg" href="{{ route('admin.dashboard') }}">
<span class="material-symbols-outlined">admin_panel_settings</span>
<span class="font-label-md text-label-md">Admin Dashboard</span>
</a>
@endif
</nav>
<div class="mt-auto pt-4 border-t border-outline-variant">
<a class="flex items-center gap-3 px-4 py-2 bg-surface-container-low rounded-lg" href="{{ route('profile.edit') }}">
<x-avatar :user="$user" class="w-8 h-8 text-[10px]"/>
<div class="overflow-hidden">
<p class="font-label-md text-label-md truncate">{{ $user->name }}</p>
<p class="text-[10px] text-on-surface-variant truncate">{{ $user->roleLabel() }}</p>
</div>
</a>
<form action="{{ route('logout') }}" class="mt-2" method="POST">
@csrf
<button class="w-full flex items-center gap-3 px-4 py-2 text-on-surface-variant hover:bg-surface-container-high transition-colors rounded-lg font-label-md text-label-md" type="submit">
<span class="material-symbols-outlined text-[20px]">logout</span>
Sign Out
</button>
</form>
</div>
</aside>
<!-- TopAppBar Shell -->
<header class="fixed top-0 right-0 w-[calc(100%-16rem)] h-16 bg-surface border-b border-outline-variant flex justify-between items-center px-8 z-40">
<form action="{{ route('projects.index') }}" class="flex items-center gap-4 flex-1" method="GET" role="search">
<div class="relative w-full max-w-md focus-within:ring-2 focus-within:ring-primary/10 rounded-lg transition-all">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
<input class="w-full bg-surface-container-low border-none rounded-lg pl-10 pr-4 py-2 font-body-md text-body-md focus:ring-0" name="q" placeholder="@yield('search_placeholder', 'Search research projects...')" type="text" value="{{ request()->routeIs('projects.index') ? request('q') : '' }}"/>
</div>
</form>
<div class="flex items-center gap-4">
@php $unread = $user->unreadNotifications()->count(); @endphp
<a class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container-low transition-colors relative" href="{{ route('notifications.index') }}" title="{{ $unread ? $unread.' unread '.Str::plural('notification', $unread) : 'Notifications' }}">
<span class="material-symbols-outlined">notifications</span>
@if ($unread > 0)
<span class="absolute top-1 right-1 min-w-4 h-4 px-1 bg-error text-on-error rounded-full text-[10px] font-bold flex items-center justify-center">{{ $unread > 9 ? '9+' : $unread }}</span>
@endif
</a>
<a class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container-low transition-colors" href="{{ route('profile.edit') }}" title="Settings">
<span class="material-symbols-outlined">settings</span>
</a>
</div>
</header>
<!-- Main Canvas Area -->
<main class="@yield('main_class', 'ml-64 mt-16 p-margin-desktop bg-surface-bright min-h-screen')">
@php $announcement = \App\Models\Setting::get('announcement'); @endphp
@if (filled($announcement))
<div class="max-w-container-max mx-auto mb-stack-md {{ str_contains($__env->yieldContent('main_class'), 'p-margin-desktop') || trim($__env->yieldContent('main_class')) === '' ? '' : 'px-margin-desktop pt-4' }}">
<x-alert :icon="\App\Models\Setting::get('announcement_level') === 'warning' ? 'warning' : 'campaign'" :kind="\App\Models\Setting::get('announcement_level') === 'warning' ? 'error' : 'info'" :title="$announcement"/>
</div>
@endif
@yield('main')
</main>
@yield('overlays')
@stack('scripts')
</body>
@endsection
