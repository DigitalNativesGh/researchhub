@extends('layouts.base')
@section('title', 'ResearchHub - Choose Research Type')
@push('styles')
<style>
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
      .peer:checked ~ .peer-checked\:type-card-selected {
        border-color: #041632 !important;
        background-color: #f3f4f5 !important;
        box-shadow: 0 4px 12px rgba(4, 22, 50, 0.08) !important;
      }
      .peer:checked ~ div .check-mark { opacity: 1; }
    </style>
@endpush
@section('body')
<body class="bg-background text-on-background font-body-md min-h-screen">
<header class="bg-surface border-b border-outline-variant h-16 fixed top-0 w-full z-50 flex items-center px-margin-desktop justify-between">
<div class="flex items-center gap-4">
<a class="hover:bg-surface-container-low p-2 rounded-full transition-colors flex items-center justify-center" href="{{ route('projects.index') }}" title="Back">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm font-bold text-primary">Create New Project</h1>
</div>
<div class="flex items-center gap-2">
<span class="font-label-md text-label-md text-on-surface-variant">Step 1 of 5</span>
<div class="w-32 h-2 bg-surface-container-high rounded-full overflow-hidden">
<div class="bg-primary h-full w-1/5 transition-all duration-500"></div>
</div>
</div>
</header>
<main class="pt-24 pb-stack-lg px-margin-desktop max-w-container-max mx-auto">
@include('projects._wizard-steps', ['current' => 1])
<form action="{{ route('projects.new.store') }}" method="POST">
@csrf
<div class="max-w-3xl mx-auto text-center mb-stack-lg">
<h2 class="font-headline-lg text-headline-lg text-primary mb-3">What type of research are you working on?</h2>
<p class="font-body-md text-body-md text-on-surface-variant">This sets the depth, length and structure of every chapter, and the price of the project. You pay once per project, before adding its title and details.</p>
</div>
<div class="max-w-3xl mx-auto mb-gutter">@include('partials.flash')</div>
@if ($errors->any())
<div class="max-w-3xl mx-auto mb-gutter">
<x-alert icon="error" kind="error" title="Choose a research type">{{ $errors->first() }}</x-alert>
</div>
@endif
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-4xl mx-auto">
@foreach (\App\Enums\DegreeLevel::cases() as $level)
<label class="relative cursor-pointer group">
<input @checked($selected === $level->value) class="peer sr-only" name="degree_level" required type="radio" value="{{ $level->value }}"/>
<div class="h-full p-6 bg-white border border-outline-variant rounded-lg transition-all hover:border-primary peer-checked:type-card-selected flex gap-5">
<div class="w-14 h-14 shrink-0 rounded-lg bg-primary/5 flex items-center justify-center">
<span class="material-symbols-outlined text-primary text-3xl">{{ $level->icon() }}</span>
</div>
<div class="flex-1 min-w-0">
<div class="flex items-start justify-between gap-3">
<h3 class="font-headline-sm text-headline-sm text-primary mb-1">{{ $level->label() }}</h3>
<div class="text-right shrink-0">
@if (in_array($level->value, $credits, true))
<span class="inline-flex items-center gap-1 px-2 py-1 bg-tertiary-fixed text-on-tertiary-fixed rounded text-[11px] font-bold uppercase tracking-wider whitespace-nowrap"><span class="material-symbols-outlined text-[14px]">verified</span>Paid</span>
@elseif ($prices[$level->value] > 0)
<p class="font-headline-sm text-headline-sm text-primary font-bold whitespace-nowrap">{{ \App\Models\Payment::format($prices[$level->value]) }}</p>
@else
<p class="font-headline-sm text-headline-sm text-primary font-bold">Free</p>
@endif
</div>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-3">{{ $level->description() }}</p>
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="inline-block px-2 py-1 bg-surface-container-high rounded text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">Typical length · {{ $level->typicalRange() }}</span>
<span class="flex items-center gap-1 text-[11px] text-on-surface-variant">{{ in_array($level->value, $credits, true) ? 'Ready to use' : 'One-off, per project' }}<span class="check-mark opacity-0 transition-opacity material-symbols-outlined text-primary text-[20px]" style="font-variation-settings: 'FILL' 1;">check_circle</span></span>
</div>
</div>
</div>
</label>
@endforeach
</div>
<div class="max-w-4xl mx-auto mt-stack-lg border-t border-outline-variant pt-gutter flex items-center justify-between">
<a class="px-6 py-3 font-label-md text-label-md text-primary hover:bg-surface-container-low transition-colors rounded-lg" href="{{ route('projects.index') }}">Cancel</a>
<button class="bg-primary text-on-primary px-8 py-4 rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all flex items-center gap-2 shadow-lg shadow-primary/10" type="submit">
                Next: Payment
                <span class="material-symbols-outlined">arrow_forward</span>
</button>
</div>
</form>
</main>
</body>
@endsection
