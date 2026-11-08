@extends('layouts.base')
@section('html_class', 'scroll-smooth')
@section('title', 'ResearchHub | Elevate Your Academic Impact')
@push('styles')
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
        }
        .gradient-subtle {
            background: linear-gradient(135deg, #f8f9fa 0%, #e7e8e9 100%);
        }
    </style>
@endpush
@section('body')
<body class="bg-surface text-on-surface antialiased overflow-x-hidden">
<!-- Marketing Header -->
<nav class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-md border-b border-outline-variant">
<div class="max-w-[1280px] mx-auto px-margin-desktop h-20 flex items-center justify-between">
<div class="flex items-center gap-2">
<div class="w-10 h-10 bg-primary flex items-center justify-center rounded-lg">
<span class="material-symbols-outlined text-on-primary">school</span>
</div>
<span class="font-headline-md text-headline-md text-primary tracking-tight">ResearchHub</span>
</div>
<div class="hidden md:flex items-center gap-8">
<a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors" href="#features">Features</a>
<a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors" href="#services">Degree Levels</a>
<a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors" href="#about">About</a>
<div class="h-6 w-[1px] bg-outline-variant mx-2"></div>
@auth
<a class="px-6 py-2.5 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all shadow-sm" href="{{ route('dashboard') }}">
                    Dashboard
                </a>
@else
<a class="font-label-md text-label-md text-primary hover:underline transition-all" href="{{ route('login') }}">Login</a>
<a class="px-6 py-2.5 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all shadow-sm" href="{{ route('register') }}">
                    Sign Up
                </a>
@endauth
</div>
<a class="md:hidden p-2 text-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}" title="{{ auth()->check() ? 'Dashboard' : 'Login' }}">
<span class="material-symbols-outlined">menu</span>
</a>
</div>
</nav>
<main class="pt-20">
<!-- Hero Section -->
<section class="relative min-h-[921px] flex items-center overflow-hidden bg-surface">
<div class="absolute inset-0 z-0 opacity-10">

</div>
<div class="max-w-[1280px] mx-auto px-margin-desktop grid grid-cols-1 md:grid-cols-2 gap-gutter items-center relative z-10 py-stack-lg">
<div class="space-y-stack-md">
<div class="inline-flex items-center gap-2 px-3 py-1 bg-secondary-container text-on-secondary-container rounded-full">
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">verified</span>
<span class="text-label-sm font-label-sm uppercase tracking-wider">Built on Your Own Sources</span>
</div>
<h1 class="font-headline-lg text-headline-lg lg:text-[56px] leading-[1.1] text-primary">
                        Elevate Your <br/> <span class="text-on-primary-container">Academic Impact</span>
</h1>
<p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl">
                        Submit your title, project details and the sources you rely on. Our research team prepares your thesis in three stages: Chapters 1–3 and a data collection instrument first, then, once you have collected and uploaded your data, the complete thesis.
                    </p>
<div class="flex flex-col sm:flex-row gap-4 pt-4">
<a class="px-8 py-4 bg-primary text-on-primary rounded-xl font-label-md text-label-md hover:bg-primary/95 transition-all shadow-lg flex items-center justify-center gap-2 group" href="{{ auth()->check() ? route('dashboard') : route('register') }}">
                            Get Started
                            <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
</a>
<a class="px-8 py-4 bg-surface border-2 border-primary text-primary rounded-xl font-label-md text-label-md hover:bg-primary/5 transition-all flex items-center justify-center gap-2" href="#features">
                            How it Works
                        </a>
</div>
</div>
<div class="relative hidden md:block">
<div class="absolute -top-12 -right-12 w-64 h-64 bg-secondary-container/30 rounded-full blur-3xl"></div>
<div class="relative z-10 bg-white p-4 rounded-2xl shadow-2xl border border-outline-variant/30 transform hover:-rotate-1 transition-transform duration-500">
<img alt="A researcher working in a bright, modern library" class="w-full h-[500px] object-cover rounded-xl grayscale-[0.2]" src="{{ \App\Support\Assets::image('home_hero') }}"/>
<div class="absolute -bottom-6 -left-6 bg-white p-6 rounded-xl shadow-xl border border-outline-variant flex items-center gap-4 animate-bounce-slow">
<div class="w-12 h-12 bg-tertiary-fixed rounded-full flex items-center justify-center">
<span class="material-symbols-outlined text-tertiary">psychology</span>
</div>
<div>
<p class="font-label-md text-label-md text-primary">Prepared by People</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Built on the sources you upload</p>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Social Proof: University Logos -->
<section class="py-16 bg-surface-container-low border-y border-outline-variant">
<div class="max-w-[1280px] mx-auto px-margin-desktop">
<p class="text-center font-label-sm text-label-sm text-on-surface-variant uppercase tracking-[0.2em] mb-10">Built for every level of graduate research</p>
<div class="flex flex-wrap justify-center items-center gap-12 md:gap-24 opacity-60 grayscale hover:grayscale-0 transition-all duration-700">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-4xl">account_balance</span>
<span class="font-headline-sm text-headline-sm italic">Undergraduate</span>
</div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-4xl">architecture</span>
<span class="font-headline-sm text-headline-sm italic">MBA</span>
</div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-4xl">menu_book</span>
<span class="font-headline-sm text-headline-sm italic">MPhil</span>
</div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-4xl">history_edu</span>
<span class="font-headline-sm text-headline-sm italic">PhD</span>
</div>
</div>
</div>
</section>
<!-- Value Propositions -->
<section class="py-stack-lg bg-surface" id="features">
<div class="max-w-[1280px] mx-auto px-margin-desktop">
<div class="text-center max-w-2xl mx-auto mb-20 space-y-4">
<h2 class="font-headline-lg text-headline-lg text-primary">Engineered for Scholarly Success</h2>
<p class="font-body-md text-body-md text-on-surface-variant">Three principles shape how our team works on every project.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
<!-- Prop 1 -->
<div class="group p-8 rounded-2xl border border-outline-variant hover:border-primary/30 hover:bg-surface-container-low transition-all duration-300">
<div class="w-14 h-14 bg-primary-container rounded-xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-on-primary-container text-3xl">groups_3</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">Grounded in Your Sources</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                            Our team works from the papers, guidelines and templates you upload, in the citation style and format your institution requires.
                        </p>
</div>
<!-- Prop 2 -->
<div class="group p-8 rounded-2xl border border-outline-variant hover:border-primary/30 hover:bg-surface-container-low transition-all duration-300">
<div class="w-14 h-14 bg-primary-container rounded-xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-on-primary-container text-3xl">account_tree</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">End-to-End Management</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                            Track every stage from submission to delivery, download your documents as soon as they are ready, and upload your data in the same place.
                        </p>
</div>
<!-- Prop 3 -->
<div class="group p-8 rounded-2xl border border-outline-variant hover:border-primary/30 hover:bg-surface-container-low transition-all duration-300">
<div class="w-14 h-14 bg-primary-container rounded-xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-on-primary-container text-3xl">gavel</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">Academic Rigor</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                            Your files stay private to you and our team. The only automated help is the optional Title Assistant, which suggests titles for you to discuss with your supervisor.
                        </p>
</div>
</div>
</div>
</section>
<!-- Featured Services (Bento Style) -->
<section class="py-stack-lg bg-surface-container-low" id="services">
<div class="max-w-[1280px] mx-auto px-margin-desktop">
<div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
<div class="space-y-4">
<span class="font-label-md text-label-md text-primary tracking-widest uppercase">Degree Levels</span>
<h2 class="font-headline-lg text-headline-lg text-primary">Structured for Your Degree</h2>
</div>
<p class="font-body-md text-body-md text-on-surface-variant max-w-md">Length, depth and methodology follow the level you are working at and your institution's guidelines.</p>
</div>
<div class="bento-grid">
<!-- Big Card -->
<div class="col-span-12 md:col-span-8 bg-white rounded-2xl p-10 border border-outline-variant flex flex-col justify-between hover:shadow-xl transition-shadow group">
<div class="max-w-md">
<div class="inline-block px-3 py-1 bg-tertiary-fixed text-on-tertiary-fixed font-label-sm text-label-sm rounded mb-6">DOCTORAL</div>
<h3 class="font-headline-md text-headline-md text-primary mb-4">PhD Thesis</h3>
<p class="font-body-md text-body-md text-on-surface-variant mb-8">
                                Extensive literature review, a full research framework and methodology, built on the papers you supply.
                            </p>
<ul class="space-y-3 mb-10">
<li class="flex items-center gap-3 font-body-sm text-body-sm">
<span class="material-symbols-outlined text-tertiary-container text-lg">check_circle</span>
                                    Chapters 1–3 and instrument delivered first
                                </li>
<li class="flex items-center gap-3 font-body-sm text-body-sm">
<span class="material-symbols-outlined text-tertiary-container text-lg">check_circle</span>
                                    Quantitative, qualitative and mixed methods
                                </li>
</ul>
</div>
<div class="flex items-center justify-between border-t border-outline-variant pt-8">
<div>
<p class="font-label-sm text-label-sm text-on-surface-variant uppercase">Typical length</p>
<p class="font-headline-md text-headline-md text-primary">70–100k words</p>
</div>
<a class="px-6 py-3 bg-primary text-on-primary rounded-lg font-label-md text-label-md group-hover:px-8 transition-all" href="{{ auth()->check() ? route('projects.new') : route('register') }}">Get Started</a>
</div>
</div>
<!-- Side Card 1 -->
<div class="col-span-12 md:col-span-4 bg-primary-container text-on-primary rounded-2xl p-8 flex flex-col justify-between hover:scale-[1.02] transition-transform">
<div>
<span class="material-symbols-outlined text-4xl mb-6 text-on-primary-container" style="font-variation-settings: 'FILL' 1;">analytics</span>
<h3 class="font-headline-sm text-headline-sm mb-4">MPhil Research</h3>
<p class="font-body-sm text-body-sm text-on-primary-container leading-relaxed">
                                Masters-level dissertations with original data: a data collection instrument first, then the complete thesis once you upload your data.
                            </p>
</div>
<div class="mt-8 pt-6 border-t border-on-primary/10">
<p class="font-label-sm text-label-sm text-on-primary-container">Typical length</p>
<p class="font-headline-sm text-headline-sm text-white">25–40k words</p>
</div>
</div>
<!-- Bottom Card 1 -->
<div class="col-span-12 md:col-span-4 bg-white rounded-2xl p-8 border border-outline-variant hover:shadow-lg transition-all group">
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">MBA Research</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-6">Business case studies and strategic analysis grounded in the literature you supply.</p>
<div class="flex items-center justify-between">
<span class="font-headline-sm text-headline-sm text-primary">12–20k words</span>
<span class="material-symbols-outlined text-primary group-hover:translate-x-2 transition-transform">east</span>
</div>
</div>
<!-- Bottom Card 2 -->
<div class="col-span-12 md:col-span-4 bg-white rounded-2xl p-8 border border-outline-variant hover:shadow-lg transition-all group">
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">Undergraduate Research</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-6">Final-year projects with clear chapters and a simple data collection instrument.</p>
<div class="flex items-center justify-between">
<span class="font-headline-sm text-headline-sm text-primary">10–15k words</span>
<span class="material-symbols-outlined text-primary group-hover:translate-x-2 transition-transform">east</span>
</div>
</div>
<!-- Bottom Card 3 (Action) -->
<div class="col-span-12 md:col-span-4 bg-secondary-container rounded-2xl p-8 flex flex-col items-center justify-center text-center">
<p class="font-label-md text-label-md text-on-secondary-container mb-2">Not sure about your title?</p>
<h3 class="font-headline-sm text-headline-sm text-primary mb-6">Try the Title Assistant</h3>
<a class="px-8 py-3 bg-primary text-on-primary rounded-lg font-label-md text-label-md w-full" href="{{ auth()->check() ? route('title-assistant') : route('register') }}">{{ auth()->check() ? 'Open Assistant' : 'Create Free Account' }}</a>
</div>
</div>
</div>
</section>
<!-- Our Promise -->
<section class="py-stack-lg bg-surface relative overflow-hidden" id="about">
<div class="absolute -right-40 top-1/2 -translate-y-1/2 w-96 h-96 bg-primary/5 rounded-full blur-3xl"></div>
<div class="max-w-[1280px] mx-auto px-margin-desktop relative z-10">
<div class="bg-primary p-12 md:p-20 rounded-[2rem] text-center text-on-primary">
<span class="material-symbols-outlined text-6xl text-primary-fixed mb-8 opacity-50" style="font-variation-settings: 'FILL' 1;">format_quote</span>
<blockquote class="font-headline-md text-headline-md italic mb-12 max-w-4xl mx-auto leading-relaxed">
                        "You bring the topic, the sources and the data. Our team does the writing, delivers it in stages, and keeps you informed at every step."
                    </blockquote>
<div class="flex flex-col items-center gap-4">
<div class="w-16 h-16 rounded-full border-2 border-primary-fixed p-1">
<div class="w-full h-full rounded-full bg-primary-container flex items-center justify-center"><span class="material-symbols-outlined text-primary-fixed text-3xl" style="font-variation-settings: 'FILL' 1;">verified</span></div>
</div>
<div>
<p class="font-label-md text-label-md text-white">Our promise</p>
<p class="font-body-sm text-body-sm text-primary-fixed">For every project, at every stage</p>
</div>
</div>
</div>
</div>
</section>
<!-- Final CTA -->
<section class="py-stack-lg bg-surface relative">
<div class="max-w-[800px] mx-auto px-margin-mobile text-center">
<h2 class="font-headline-lg text-headline-lg text-primary mb-6">Ready to Accelerate Your Research?</h2>
<p class="font-body-lg text-body-lg text-on-surface-variant mb-12">Create an account, choose your research type, and submit your title, details and sources. We will take it from there.</p>
<div class="flex flex-col sm:flex-row gap-4 justify-center">
<a class="px-10 py-5 bg-primary text-on-primary rounded-xl font-label-md text-label-md hover:scale-105 transition-all shadow-xl" href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ auth()->check() ? 'Open Dashboard' : 'Create Free Account' }}</a>
<a class="px-10 py-5 bg-white border border-outline-variant text-primary rounded-xl font-label-md text-label-md hover:bg-surface-container-low transition-all" href="{{ route('login') }}">Sign In</a>
</div>
</div>
</section>
</main>
<!-- Footer -->
<footer class="bg-primary text-on-primary-container py-20 border-t border-on-primary/10">
<div class="max-w-[1280px] mx-auto px-margin-desktop">
<div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
<div class="col-span-1 md:col-span-1 space-y-6">
<div class="flex items-center gap-2">
<div class="w-8 h-8 bg-on-primary-container flex items-center justify-center rounded">
<span class="material-symbols-outlined text-primary text-lg">school</span>
</div>
<span class="font-headline-sm text-headline-sm text-white">ResearchHub</span>
</div>
<p class="font-body-sm text-body-sm text-on-primary-container max-w-xs leading-relaxed">
                        Thesis support from our research team, built on the sources you upload.
                    </p>
<div class="flex gap-4">
<a class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-white hover:bg-on-primary-container hover:text-primary transition-all" href="#">
<span class="material-symbols-outlined text-xl">share</span>
</a>
<a class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-white hover:bg-on-primary-container hover:text-primary transition-all" href="#">
<span class="material-symbols-outlined text-xl">public</span>
</a>
<a class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-white hover:bg-on-primary-container hover:text-primary transition-all" href="#">
<span class="material-symbols-outlined text-xl">alternate_email</span>
</a>
</div>
</div>
<div class="space-y-6">
<h4 class="font-label-md text-label-md text-white uppercase tracking-widest">Platform</h4>
<ul class="space-y-4 font-body-sm text-body-sm">
<li><a class="hover:text-white transition-colors" href="#features">Features</a></li>
<li><a class="hover:text-white transition-colors" href="#services">Degree Levels</a></li>
<li><a class="hover:text-white transition-colors" href="#about">How Citations Work</a></li>
<li><a class="hover:text-white transition-colors" href="{{ route('register') }}">Create Account</a></li>
</ul>
</div>
<div class="space-y-6">
<h4 class="font-label-md text-label-md text-white uppercase tracking-widest">Company</h4>
<ul class="space-y-4 font-body-sm text-body-sm">
<li><a class="hover:text-white transition-colors" href="#about">About Us</a></li>
<li><a class="hover:text-white transition-colors" href="#about">Academic Integrity</a></li>
<li><a class="hover:text-white transition-colors" href="#">Contact</a></li>
</ul>
</div>
<div class="space-y-6">
<h4 class="font-label-md text-label-md text-white uppercase tracking-widest">Legal</h4>
<ul class="space-y-4 font-body-sm text-body-sm">
<li><a class="hover:text-white transition-colors" href="#">Privacy Policy</a></li>
<li><a class="hover:text-white transition-colors" href="#">Terms of Service</a></li>
</ul>
</div>
</div>
<div class="pt-8 border-t border-on-primary/10 flex flex-col md:flex-row justify-between items-center gap-4">
<p class="font-label-sm text-label-sm text-on-primary-container opacity-60">© {{ date('Y') }} ResearchHub. All rights reserved.</p>
<div class="flex items-center gap-6">
<span class="flex items-center gap-2 font-label-sm text-label-sm text-on-primary-container">
<span class="material-symbols-outlined text-sm">lock</span>
                        Private by Default
                    </span>
<span class="flex items-center gap-2 font-label-sm text-label-sm text-on-primary-container">
<span class="material-symbols-outlined text-sm">verified_user</span>
                        Your Files Stay Yours
                    </span>
</div>
</div>
</div>
</footer>
<script>
        // Micro-interactions and scroll handling
        document.addEventListener('DOMContentLoaded', () => {
            const header = document.querySelector('nav');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('shadow-md');
                } else {
                    header.classList.remove('shadow-md');
                }
            });

            // Smooth scroll anchor behavior
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const target = this.getAttribute('href').length > 1 ? document.querySelector(this.getAttribute('href')) : null;
                    if (!target) {
                        return;
                    }
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });
        });
    </script>
</body>
@endsection
