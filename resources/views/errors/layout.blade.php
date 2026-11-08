@extends('layouts.base')
@section('title')@yield('code') · @yield('heading') | ResearchHub @endsection
@section('body')
<body class="bg-background min-h-screen flex items-center justify-center p-margin-mobile md:p-margin-desktop">
<main class="w-full max-w-[520px] text-center">
<div class="mb-stack-lg flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-primary text-[32px]">school</span>
<span class="font-headline-md text-headline-md text-primary tracking-tight">ResearchHub</span>
</div>
<div class="bg-surface-container-lowest border border-outline-variant rounded-lg p-stack-md md:p-stack-lg ambient-shadow">
<div class="w-14 h-14 mx-auto rounded-xl @yield('icon_class', 'bg-secondary-container/40 text-secondary') flex items-center justify-center mb-stack-sm">
<span class="material-symbols-outlined text-[28px]">@yield('icon')</span>
</div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant mb-2">Error @yield('code')</p>
<h1 class="font-headline-md text-headline-md text-primary mb-2">@yield('heading')</h1>
<p class="font-body-md text-body-md text-on-surface-variant mb-stack-md">@yield('text')</p>
<div class="flex flex-col sm:flex-row gap-3 justify-center">
@hasSection('primary')
@yield('primary')
@else
<a class="bg-primary text-on-primary px-6 py-3 rounded-lg font-label-md text-label-md flex items-center justify-center gap-2 hover:bg-primary-container transition-all" href="{{ auth()->check() ? route('dashboard') : url('/') }}">
<span class="material-symbols-outlined text-[20px]">{{ auth()->check() ? 'dashboard' : 'home' }}</span>{{ auth()->check() ? 'Go to Dashboard' : 'Go to Home' }}
</a>
@endif
<button class="flex items-center justify-center gap-2 px-6 py-3 border border-outline-variant rounded-lg font-label-md text-label-md text-on-surface hover:bg-surface-container-low transition-colors" onclick="history.back()" type="button">
<span class="material-symbols-outlined text-[20px]">arrow_back</span>Go Back
</button>
</div>
</div>
</main>
</body>
@endsection
