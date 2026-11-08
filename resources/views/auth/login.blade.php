@extends('layouts.base')
@section('title', 'Sign in to ResearchHub')
@push('styles')
<style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa; /* background token */
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .ambient-shadow {
            box-shadow: 0 10px 40px -10px rgba(4, 22, 50, 0.08);
        }
        .input-ring:focus {
            outline: none;
            border-color: #041632;
            box-shadow: 0 0 0 2px rgba(4, 22, 50, 0.1);
        }
    </style>
@endpush
@section('body')
<body class="flex min-h-screen items-center justify-center p-margin-mobile md:p-margin-desktop bg-background selection:bg-primary-fixed selection:text-on-primary-fixed">
<!-- Login Container -->
<main class="w-full max-w-[440px] animate-in fade-in slide-in-from-bottom-4 duration-700">
<!-- Login Card -->
<div class="bg-surface-container-lowest border border-outline-variant rounded-lg p-stack-md md:p-stack-lg ambient-shadow">
<!-- Brand & Header -->
<div class="flex flex-col items-center text-center mb-stack-lg">
<div class="mb-stack-sm flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[32px]">school</span>
<span class="font-headline-md text-headline-md text-primary tracking-tight">ResearchHub</span>
</div>
<h1 class="font-headline-sm text-headline-sm text-on-surface">Sign in to ResearchHub</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-2">Access your academic workstation</p>
</div>
<!-- Form -->
@if (session('status'))
<div class="flex items-start gap-3 p-4 mb-stack-md bg-tertiary-fixed/40 border border-tertiary-fixed text-on-tertiary-fixed-variant rounded-lg">
<span class="material-symbols-outlined text-[20px]">check_circle</span>
<p class="font-body-sm text-body-sm">{{ session('status') }}</p>
</div>
@endif
<form action="{{ route('login') }}" class="space-y-stack-md" id="loginForm" method="POST">
@csrf
<!-- Email Field -->
<div class="space-y-2">
<label class="font-label-md text-label-md text-on-surface-variant block" for="email">Email Address</label>
<div class="relative group">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px] transition-colors group-focus-within:text-primary">mail</span>
<input autocomplete="username" autofocus class="w-full pl-10 pr-4 py-3 bg-white border {{ $errors->has('email') ? 'border-error' : 'border-outline-variant' }} rounded-lg font-body-md text-body-md text-on-surface input-ring transition-all" id="email" name="email" placeholder="e.g. researcher@university.edu" required="" type="email" value="{{ old('email') }}"/>
</div>
<x-field-error name="email"/>
</div>
<!-- Password Field -->
<div class="space-y-2">
<div class="flex justify-between items-center">
<label class="font-label-md text-label-md text-on-surface-variant" for="password">Password</label>
<a class="font-label-md text-label-md text-secondary hover:text-primary transition-colors" href="{{ route('password.request') }}">Forgot password?</a>
</div>
<div class="relative group">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px] transition-colors group-focus-within:text-primary">lock</span>
<input autocomplete="current-password" class="w-full pl-10 pr-12 py-3 bg-white border border-outline-variant rounded-lg font-body-md text-body-md text-on-surface input-ring transition-all" id="password" name="password" placeholder="••••••••" required="" type="password"/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors" onclick="togglePassword()" type="button">
<span class="material-symbols-outlined text-[20px]" id="passIcon">visibility</span>
</button>
</div>
</div>
<!-- Remember Me -->
<div class="flex items-center gap-2 pt-1">
<input class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary/20 transition-all cursor-pointer" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))/>
<label class="font-body-sm text-body-sm text-on-surface-variant cursor-pointer select-none" for="remember">Remember me for 30 days</label>
</div>
<!-- Action Button -->
<button class="w-full bg-primary text-on-primary py-4 rounded-lg font-label-md text-label-md flex items-center justify-center gap-2 hover:bg-primary-container transition-all active:scale-[0.98]" type="submit">
<span>Sign In</span>
<span class="material-symbols-outlined text-[20px]">arrow_forward</span>
</button>
</form>
</div>
<!-- Footer Link -->
<p class="text-center mt-stack-md font-body-sm text-body-sm text-on-surface-variant">
            Don't have an account? 
            <a class="text-primary font-label-md text-label-md hover:underline underline-offset-4" href="{{ route('register') }}">Create an account</a>
</p>
<!-- Page Footer/Metadata -->
<footer class="mt-stack-lg text-center space-y-2 opacity-50">
<p class="font-label-sm text-label-sm text-on-surface-variant">© {{ date('Y') }} ResearchHub Academic Precision System</p>
<div class="flex justify-center gap-4 text-[10px] font-label-sm text-on-surface-variant uppercase tracking-widest">
<a class="hover:text-primary" href="#">Privacy</a>
<a class="hover:text-primary" href="#">Security</a>
<a class="hover:text-primary" href="#">Terms</a>
</div>
</footer>
</main>
<!-- Background Decoration (Subtle/Abstract) -->
<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
<div class="absolute top-[-10%] right-[-5%] w-[400px] h-[400px] rounded-full bg-primary/5 blur-[120px]"></div>
<div class="absolute bottom-[-10%] left-[-5%] w-[400px] h-[400px] rounded-full bg-secondary-container/20 blur-[120px]"></div>
</div>
<script>
        function togglePassword() {
            const passInput = document.getElementById('password');
            const passIcon = document.getElementById('passIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                passIcon.innerText = 'visibility_off';
            } else {
                passInput.type = 'password';
                passIcon.innerText = 'visibility';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = e.target.querySelector('button[type="submit"]');

            // Interaction Feedback
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Authenticating...';
            btn.disabled = true;
            btn.classList.add('opacity-80');
        });
    </script>
</body>
@endsection
