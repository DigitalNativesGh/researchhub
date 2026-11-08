@extends('layouts.base')
@section('title', 'ResearchHub Onboarding - Role Selection')
@push('styles')
<style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }
        .role-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .role-card.selected {
            border-color: #041632;
            background-color: #c8dfff;
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(4, 22, 50, 0.1);
        }
        .role-card.selected .icon-bg {
            background-color: #041632;
            color: #ffffff;
        }
        .role-card.selected .material-symbols-outlined {
            font-variation-settings: 'FILL' 1;
        }
    </style>
@endpush
@section('body')
<body class="min-h-screen flex items-center justify-center p-margin-mobile md:p-margin-desktop">
<!-- Main Content Container -->
<main class="w-full max-w-[800px] flex flex-col items-center">
<!-- Logo/Header Area -->
<div class="mb-stack-lg text-center animate-fade-in">
<div class="mb-stack-sm inline-flex items-center justify-center">
<div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center text-on-primary">
<span class="material-symbols-outlined text-3xl">school</span>
</div>
</div>
<h1 class="font-headline-lg text-headline-lg text-primary mb-2">
                Welcome to ResearchHub. Select your role to get started.
            </h1>
<p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl mx-auto">
                Tailoring your experience for academic excellence.
            </p>
</div>
<form action="{{ route('onboarding.store') }}" class="w-full flex flex-col items-center" id="role-form" method="POST">
@csrf
<input id="role-input" name="role" type="hidden" value="{{ old('role', auth()->user()->role) }}"/>
<!-- Role Selection Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-gutter w-full mb-stack-lg">
@foreach ($roles as $key => [$label, $icon, $description])
<!-- Card {{ $loop->iteration }}: {{ $label }} -->
<button class="role-card group flex flex-col items-start p-stack-md bg-surface border border-outline-variant rounded-xl text-left hover:border-primary/50 hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/20 {{ old('role', auth()->user()->role) === $key ? 'selected' : '' }}" data-role="{{ $key }}" onclick="selectRole(this)" type="button">
<div class="icon-bg w-12 h-12 rounded-lg bg-surface-container-high flex items-center justify-center text-primary mb-stack-sm transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" data-icon="{{ $icon }}">{{ $icon }}</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-primary mb-1">{{ $label }}</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $description }}</p>
</button>
@endforeach
</div>
<x-field-error name="role"/>
<!-- Action Section -->
<div class="flex flex-col items-center w-full max-w-sm">
<button class="w-full bg-primary text-on-primary py-4 rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all {{ old('role', auth()->user()->role) ? '' : 'opacity-50 cursor-not-allowed' }} shadow-lg" @disabled(! old('role', auth()->user()->role)) id="continue-btn" type="submit">
                Continue
            </button>
<p class="mt-stack-sm font-label-sm text-label-sm text-outline">
                Signed in as {{ auth()->user()->email }}. <button class="text-primary hover:underline" form="logout-form" type="submit">Not you? Sign out</button>
</p>
</div>
</form>
<form action="{{ route('logout') }}" class="hidden" id="logout-form" method="POST">@csrf</form>
</main>
<!-- Visual Accents (Background Decorations) -->
<div class="fixed top-0 left-0 w-full h-full pointer-events-none -z-10 opacity-40">
<div class="absolute top-10 left-10 w-64 h-64 bg-secondary-container rounded-full blur-[100px]"></div>
<div class="absolute bottom-10 right-10 w-96 h-96 bg-primary-fixed rounded-full blur-[120px]"></div>
</div>
<!-- Micro-interaction Script -->
<script>
        function selectRole(element) {
            // Remove selection from all cards
            const cards = document.querySelectorAll('.role-card');
            cards.forEach(card => card.classList.remove('selected'));
            
            // Add selection to clicked card
            element.classList.add('selected');

            // Enable Continue Button
            const continueBtn = document.getElementById('continue-btn');
            continueBtn.disabled = false;
            continueBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            continueBtn.classList.add('hover:translate-y-[-2px]', 'active:scale-95');

            document.getElementById('role-input').value = element.getAttribute('data-role');
        }

        // Action for continue
        document.getElementById('role-form').addEventListener('submit', function() {
            const btn = document.getElementById('continue-btn');
            if (!btn.disabled) {
                btn.innerHTML = '<span class="flex items-center justify-center gap-2"><svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Initializing Experience...</span>';
            }
        });
    </script>
</body>
@endsection
