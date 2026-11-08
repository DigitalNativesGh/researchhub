@extends('layouts.base')
@section('title', 'ResearchHub - Project Creation Wizard')
@push('styles')
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
    </style>
@endpush
@section('body')
<body class="bg-background text-on-surface min-h-screen flex flex-col overflow-x-hidden">
<!-- Top AppBar (Simplified for focused task) -->
<header class="fixed top-0 left-0 right-0 h-16 bg-surface border-b border-outline-variant z-40 flex items-center justify-between px-8">
<div class="flex items-center gap-4">
<a class="p-2 hover:bg-surface-container-low transition-colors rounded-full" href="{{ route('projects.setup.sources', $project) }}" title="Back to sources">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm font-bold text-primary">ResearchHub</span>
</div>
</div>
<div class="flex items-center gap-6">
<div class="flex items-center gap-2 text-on-surface-variant font-label-md text-label-md">
<span class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center">1</span>
<span class="hidden sm:inline">Type</span>
<span class="text-outline-variant">/</span>
<span class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center">2</span>
<span class="hidden sm:inline">Payment</span>
<span class="text-outline-variant">/</span>
<span class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center">3</span>
<span class="hidden sm:inline">Details</span>
<span class="text-outline-variant">/</span>
<span class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center">4</span>
<span class="hidden sm:inline">Sources</span>
<span class="text-outline-variant">/</span>
<span class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center">5</span>
<span class="text-primary font-bold">Review</span>
</div>
</div>
</header>
<main class="mt-16 flex-1 flex justify-center py-margin-desktop px-margin-mobile">
<div class="max-w-[1000px] w-full grid grid-cols-1 lg:grid-cols-12 gap-gutter items-start">
<!-- Left Column: Summary -->
<div class="lg:col-span-5 space-y-stack-md">
<section class="bg-surface border border-outline-variant rounded-lg p-stack-md shadow-sm">
<h2 class="font-headline-sm text-headline-sm text-primary mb-stack-sm">Project Summary</h2>
<p class="text-on-surface-variant font-body-sm text-body-sm mb-6">Please review your project details before creating it.</p>
<div class="space-y-4">
@foreach ([
    ['Project Title', $project->title, false],
    ['Degree Level', $project->degree_level->label(), true],
    ['Field of Study', $project->discipline, false],
    ['Research Approach', $project->research_type->label(), false],
    ['Citation Style', $project->citation_style->label(), false],
    ['Institution', $project->institution ?: '—', false],
    ['Required Length', $project->word_count_target ? number_format($project->word_count_target).' words' : 'Typical for the degree level', false],
    ['Deadline', $project->deadline?->format('j F Y') ?? 'None given', false],
    ['Sources', $project->papers->where('kind', 'source')->count().' '.Str::plural('file', $project->papers->where('kind', 'source')->count()).($project->papers->where('kind', 'guidelines')->count() ? ' · '.$project->papers->where('kind', 'guidelines')->count().' guideline file(s)' : ''), false],
] as [$label, $value, $bold])
<div class="pb-4 {{ $loop->last ? '' : 'border-b border-outline-variant' }}">
<span class="text-label-sm font-label-sm uppercase tracking-wider text-outline block mb-1">{{ $label }}</span>
<span class="font-body-md text-body-md text-on-surface {{ $bold ? 'font-semibold' : '' }}">{{ $value }}</span>
</div>
@endforeach
</div>
<a class="mt-4 inline-flex items-center gap-1 text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('projects.edit', $project) }}"><span class="material-symbols-outlined text-[18px]">edit</span>Edit details</a>
</section>
@if ($project->papers->where('kind', 'source')->count() < $minSources)
<x-alert icon="info" kind="warn" title="Add your sources">Upload at least {{ $minSources }} source {{ Str::plural('paper', $minSources) }} before submitting. Sources are locked once the project is submitted.</x-alert>
@endif
</div>
<!-- Right Column: How it works + create -->
<div class="lg:col-span-7">
<section class="bg-surface-container-lowest border border-outline-variant rounded-lg p-stack-md shadow-sm">
<h2 class="font-headline-sm text-headline-sm text-primary mb-stack-sm">What happens next</h2>
<p class="text-on-surface-variant font-body-sm text-body-sm mb-6">Our team prepares your thesis in three stages. You can follow each one from your project page.</p>
<div class="relative flex flex-col gap-6 ml-2 mb-stack-md">
<div class="absolute left-3 top-2 bottom-2 w-[2px] bg-outline-variant"></div>
@foreach ([
    ['Stage 1 · Chapters 1–3 and instrument', 'Our team prepares your introduction, literature review and methodology from your sources, plus your '.match ($project->research_type) { \App\Enums\ResearchType::Qualitative => 'interview guide', \App\Enums\ResearchType::Mixed => 'questionnaire and interview guide', default => 'questionnaire' }.'.'],
    ['Stage 2 · Your data', 'Collect data with the instrument and upload it here.'],
    ['Stage 3 · Complete thesis', 'Our team prepares the complete thesis, Chapters 1–5 with references and appendices, from your data.'],
] as [$title, $text])
<div class="relative flex gap-4">
<div class="z-10 w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container border-4 border-surface-container-lowest flex items-center justify-center text-[10px] font-bold">{{ $loop->iteration }}</div>
<div><p class="font-label-md text-label-md text-on-surface">{{ $title }}</p><p class="text-body-sm text-on-surface-variant">{{ $text }}</p></div>
</div>
@endforeach
</div>
<form action="{{ route('projects.submit', $project) }}" class="space-y-6" id="createForm" method="POST">
@csrf
<div class="flex items-start gap-3 p-4 bg-surface-container-low border {{ $errors->has('confirm') ? 'border-error' : 'border-outline-variant' }} rounded-lg">
<input class="mt-0.5 w-4 h-4 text-primary border-outline-variant rounded focus:ring-primary/20" id="confirm" name="confirm" required="" type="checkbox" value="1"/>
<label class="font-body-sm text-body-sm text-on-surface" for="confirm">My title, details and sources are correct. I understand that my sources are locked once I submit, unless your team asks me for more.</label>
</div>
<x-field-error name="confirm"/>
<button class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary py-4 rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all shadow-sm active:scale-[0.98] disabled:opacity-50" @disabled($project->papers->where('kind', 'source')->count() < $minSources) id="createButton" type="submit">
<span class="material-symbols-outlined">rocket_launch</span>
Submit My Project
</button>
</form>
</section>
</div>
</div>
</main>
<script>
        document.getElementById('createForm').addEventListener('submit', () => {
            const btn = document.getElementById('createButton');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Submitting...';
        });
    </script>
</body>
@endsection
