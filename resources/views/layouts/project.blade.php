{{-- Shared shell for the researcher's project pages. Shows only researcher-facing status, never internal details. --}}
@extends('layouts.page')

@php
    $workflow = $project->workflow_status;
    $dataOpen = in_array($workflow->stage(), [2, 3], true) || $project->data_submitted_at !== null || \App\Services\Workflow\ProjectWorkflow::dataOpen($project);
    $stages = array_values(array_filter([
        ['overview', 'Overview', route('projects.show', $project)],
        ['sources', 'Sources', route('projects.sources', $project)],
        $dataOpen ? ['data', 'Your Data', route('projects.data', $project)] : null,
    ]));
    $stage = trim($__env->yieldContent('stage_key', 'overview'));
@endphp

@section('content')
<div class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl" data-poll-url="{{ route('projects.progress', $project) }}" data-poll-fingerprint="{{ $fingerprint ?? '' }}">
<div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
<div class="space-y-2">
<div class="flex items-center gap-3 flex-wrap">
<a class="flex items-center gap-1 text-on-surface-variant hover:text-primary text-[12px] font-medium" href="{{ route('projects.index') }}"><span class="material-symbols-outlined text-[16px]">arrow_back</span>My Projects</a>
<span class="text-outline-variant">/</span>
<span class="bg-surface-container-high px-2 py-1 rounded text-[12px] font-bold text-primary">{{ $project->reference() }}</span>
<span class="text-on-surface-variant text-[12px] font-medium">{{ $project->metaLine() }}</span>
</div>
<h2 class="font-headline-md text-headline-md text-primary leading-tight">{{ $project->title }}</h2>
</div>
<div class="flex items-center gap-3 flex-wrap">
@hasSection('header_actions')
@yield('header_actions')
@else
<x-badge :kind="$workflow->badge()">{{ $workflow->researcherLabel() }}</x-badge>
@endif
</div>
</div>
<nav class="flex items-center gap-2 mt-6 overflow-x-auto pb-1 custom-scrollbar">
@foreach ($stages as [$key, $label, $href])
@if ($key === $stage)
<a aria-current="page" class="bg-primary text-on-primary px-4 py-1.5 rounded-full font-label-sm text-label-sm whitespace-nowrap" href="{{ $href }}">{{ $label }}</a>
@else
<a class="bg-surface-container-high text-on-surface-variant px-4 py-1.5 rounded-full font-label-sm text-label-sm hover:bg-surface-container-highest transition-colors whitespace-nowrap" href="{{ $href }}">{{ $label }}</a>
@endif
@endforeach
</nav>
</div>
@include('partials.responsibility-notice')
@yield('stage')
@endsection
