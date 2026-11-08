@extends('layouts.base')
@section('title', 'Sign Up | ResearchHub')
@push('styles')
<style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .ambient-shadow {
            box-shadow: 0 20px 40px -10px rgba(4, 22, 50, 0.08);
        }
        .scholar-ring:focus {
            outline: none;
            border-color: #041632;
            box-shadow: 0 0 0 2px rgba(4, 22, 50, 0.1);
        }
    </style>
@endpush
@section('body')
<body class="bg-background min-h-screen flex items-center justify-center p-margin-mobile md:p-margin-desktop overflow-x-hidden">
<!-- Decorative Subtle Background Pattern -->
<div class="fixed inset-0 pointer-events-none opacity-[0.03] z-0">
<svg height="100%" width="100%" xmlns="http://www.w3.org/2000/svg">
<defs>
<pattern height="40" id="grid" patternunits="userSpaceOnUse" width="40">
<path d="M 40 0 L 0 0 0 40" fill="none" stroke="currentColor" stroke-width="1"></path>
</pattern>
</defs>
<rect fill="url(#grid)" height="100%" width="100%"></rect>
</svg>
</div>
<!-- Main Content Canvas -->
<main class="relative z-10 w-full max-w-[480px]">
<!-- Auth Card -->
<div class="bg-surface-container-lowest border border-outline-variant rounded-lg ambient-shadow p-stack-md md:p-stack-lg flex flex-col">
<!-- Brand Identity Section -->
<div class="flex flex-col items-center mb-stack-lg">
<div class="mb-stack-sm flex items-center justify-center w-12 h-12 bg-primary rounded-lg">
<span class="material-symbols-outlined text-on-primary text-[28px]" style="font-variation-settings: 'FILL' 1;">school</span>
</div>
<h1 class="font-headline-md text-headline-md text-primary mb-1">ResearchHub</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant">Academic Excellence</p>
</div>
<!-- Header -->
<div class="mb-stack-md">
<h2 class="font-headline-sm text-headline-sm text-on-surface">Create your account</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Thesis support from our research team, delivered in clear stages.</p>
</div>
<!-- Registration Form -->
<form action="{{ route('register') }}" class="space-y-stack-md" method="POST">
@csrf
<!-- Full Name -->
<div class="flex flex-col gap-2">
<label class="font-label-md text-label-md text-on-surface-variant" for="full_name">Full Name</label>
<input autocomplete="name" autofocus class="w-full h-12 px-4 bg-surface-container-lowest border {{ $errors->has('name') ? 'border-error' : 'border-outline' }} rounded-lg font-body-md text-body-md text-on-surface scholar-ring transition-all placeholder:text-outline-variant" id="full_name" name="name" placeholder="Dr. Jane Smith" required="" type="text" value="{{ old('name') }}"/>
<x-field-error name="name"/>
</div>
<!-- Institutional Email -->
<div class="flex flex-col gap-2">
<label class="font-label-md text-label-md text-on-surface-variant" for="email">Institutional Email</label>
<div class="relative">
<input autocomplete="email" class="w-full h-12 px-4 pr-10 bg-surface-container-lowest border {{ $errors->has('email') ? 'border-error' : 'border-outline' }} rounded-lg font-body-md text-body-md text-on-surface scholar-ring transition-all placeholder:text-outline-variant" id="email" name="email" placeholder="jane.smith@university.edu" required="" type="email" value="{{ old('email') }}"/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">alternate_email</span>
</div>
<x-field-error name="email"/>
</div>
<!-- Password -->
<div class="flex flex-col gap-2">
<label class="font-label-md text-label-md text-on-surface-variant" for="password">Password</label>
<div class="relative">
<input autocomplete="new-password" class="w-full h-12 px-4 pr-10 bg-surface-container-lowest border {{ $errors->has('password') ? 'border-error' : 'border-outline' }} rounded-lg font-body-md text-body-md text-on-surface scholar-ring transition-all placeholder:text-outline-variant" id="password" minlength="8" name="password" placeholder="••••••••" required="" type="password"/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" type="button">
<span class="material-symbols-outlined text-[20px]">visibility</span>
</button>
</div>
<x-field-error name="password"/>
</div>
<!-- Terms & Conditions Checkbox -->
<div class="flex items-start gap-3 pt-2">
<div class="flex items-center h-5">
<input class="w-4 h-4 border-outline rounded text-primary focus:ring-primary/20 transition-all cursor-pointer" id="terms" name="terms" required="" type="checkbox" value="1" @checked(old('terms'))/>
</div>
<label class="font-body-sm text-body-sm text-on-surface-variant leading-tight" for="terms">
                        I agree to the <a class="text-primary font-semibold hover:underline" href="#">Terms of Service</a> and <a class="text-primary font-semibold hover:underline" href="#">Privacy Policy</a>
</label>
</div>
<x-field-error name="terms"/>
<!-- Primary Action -->
<button class="w-full h-12 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-2" type="submit">
                    Create Account
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
</button>
</form>
<!-- Footer Link -->
<div class="mt-stack-lg pt-stack-md border-t border-outline-variant text-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">
                    Already have an account? 
                    <a class="text-primary font-semibold hover:underline ml-1" href="{{ route('login') }}">Log in</a>
</p>
</div>
</div>
<!-- Support & Footer -->
<footer class="mt-stack-lg text-center">
<div class="flex items-center justify-center gap-4 text-on-surface-variant mb-2">
<a class="font-label-sm text-label-sm hover:text-primary transition-colors" href="#">Support</a>
<span class="w-1 h-1 rounded-full bg-outline-variant"></span>
<a class="font-label-sm text-label-sm hover:text-primary transition-colors" href="#">Documentation</a>
<span class="w-1 h-1 rounded-full bg-outline-variant"></span>
<a class="font-label-sm text-label-sm hover:text-primary transition-colors" href="#">Security</a>
</div>
<p class="font-body-sm text-[10px] text-outline uppercase tracking-widest">© {{ date('Y') }} ResearchHub Academic Precision System</p>
</footer>
</main>
<!-- Micro-interaction script -->
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.classList.add('scale-[1.01]');
                });
                input.addEventListener('blur', () => {
                    input.parentElement.classList.remove('scale-[1.01]');
                });
            });

            // Toggle password visibility
            const togglePass = document.querySelector('button[type="button"]');
            const passInput = document.getElementById('password');
            if(togglePass && passInput) {
                togglePass.addEventListener('click', () => {
                    const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passInput.setAttribute('type', type);
                    togglePass.querySelector('span').textContent = type === 'password' ? 'visibility' : 'visibility_off';
                });
            }
        });
    </script>
</body>
@endsection
