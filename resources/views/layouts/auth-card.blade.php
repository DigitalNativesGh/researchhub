{{-- Card layout shared by the auth pages that are not in the design files (same pattern as the sign-in page). --}}
@extends('layouts.base')

@section('body')
<body class="flex min-h-screen items-center justify-center p-margin-mobile md:p-margin-desktop bg-background selection:bg-primary-fixed selection:text-on-primary-fixed">
<main class="w-full max-w-[440px]">
<div class="bg-surface-container-lowest border border-outline-variant rounded-lg p-stack-md md:p-stack-lg ambient-shadow">
<!-- Brand & Header -->
<div class="flex flex-col items-center text-center mb-stack-lg">
<div class="mb-stack-sm flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[32px]">school</span>
<span class="font-headline-md text-headline-md text-primary tracking-tight">ResearchHub</span>
</div>
@hasSection('icon')
<div class="w-14 h-14 rounded-xl bg-secondary-container/40 text-secondary flex items-center justify-center mb-stack-sm">
<span class="material-symbols-outlined text-[28px]">@yield('icon')</span>
</div>
@endif
<h1 class="font-headline-sm text-headline-sm text-on-surface">@yield('heading')</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-2">@yield('sub')</p>
</div>
@if (session('status'))
<div class="flex items-start gap-3 p-4 mb-stack-md bg-tertiary-fixed/40 border border-tertiary-fixed text-on-tertiary-fixed-variant rounded-lg">
<span class="material-symbols-outlined text-[20px]">check_circle</span>
<p class="font-body-sm text-body-sm">{{ session('status') }}</p>
</div>
@endif
@yield('form')
</div>
@yield('below')
<footer class="mt-stack-lg text-center space-y-2 opacity-50">
<p class="font-label-sm text-label-sm text-on-surface-variant">© {{ date('Y') }} ResearchHub Academic Precision System</p>
<div class="flex justify-center gap-4 text-[10px] font-label-sm text-on-surface-variant uppercase tracking-widest">
<a class="hover:text-primary" href="#">Privacy</a>
<a class="hover:text-primary" href="#">Security</a>
<a class="hover:text-primary" href="#">Terms</a>
</div>
</footer>
</main>
</body>
@endsection
