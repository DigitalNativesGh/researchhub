@extends('layouts.app')
@section('title', 'My Projects | ResearchHub')
@section('body_class', 'bg-background text-on-surface overflow-hidden')
@section('main_class', 'ml-64 mt-16 p-8 min-h-screen overflow-y-auto')
@push('styles')
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c5c6ce;
            border-radius: 10px;
        }
    </style>
@endpush
@section('nav', 'projects')
@section('search_placeholder', 'Search your projects...')
@section('main')
<div class="max-w-7xl mx-auto">
@include('partials.flash')
<!-- Header Section -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
<div>
<h2 class="font-headline-lg text-headline-lg text-primary">My Research Portfolio</h2>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Your thesis projects, from submission to final delivery.</p>
</div>
<a class="bg-primary text-on-primary px-6 py-2.5 rounded-lg font-label-md text-label-md flex items-center gap-2 hover:bg-primary-container transition-all shadow-sm" href="{{ route('projects.new') }}">
<span class="material-symbols-outlined text-base">add</span>
                    New Project
                </a>
</div>
@if ($total === 0)
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md mb-gutter">
<x-empty action="Create Your First Project" :href="route('projects.new')" icon="note_add" title="No projects yet">Start a project, add your title, details and the sources you want to build on, and our team will deliver your thesis in stages.</x-empty>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
@foreach ([['upload_file', 'Bring your own sources', 'Upload the papers, guidelines and templates your thesis should follow.'], ['task_alt', 'Chapters 1–3 first', 'Our team delivers Chapters 1–3 and your data collection instrument first.'], ['workspace_premium', 'Then the full thesis', 'Upload the data you collect and we deliver the complete thesis.']] as [$icon, $heading, $text])
<div class="p-5 border border-outline-variant rounded-xl bg-surface-container-low/30">
<div class="flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-xl">{{ $icon }}</span><h4 class="font-label-md text-label-md text-on-surface">{{ $heading }}</h4></div>
<p class="text-body-sm text-on-surface-variant">{{ $text }}</p>
</div>
@endforeach
</div>
@else
<!-- Filters Bar -->
<div class="flex items-center gap-3 mb-6 overflow-x-auto pb-2 scrollbar-hide">
<span class="font-label-md text-label-md text-on-surface-variant mr-2">Filter by:</span>
@foreach ($filters as $key => [$label, $count])
@if ($status === $key)
<a class="bg-primary text-on-primary px-4 py-1.5 rounded-full font-label-sm text-label-sm whitespace-nowrap" href="{{ route('projects.index', array_filter(['status' => $key === 'all' ? null : $key, 'sort' => $sort, 'q' => $q])) }}">{{ $label }} ({{ $count }})</a>
@else
<a class="bg-surface-container-high text-on-surface-variant px-4 py-1.5 rounded-full font-label-sm text-label-sm hover:bg-surface-container-highest transition-colors whitespace-nowrap" href="{{ route('projects.index', array_filter(['status' => $key === 'all' ? null : $key, 'sort' => $sort, 'q' => $q])) }}">{{ $label }} ({{ $count }})</a>
@endif
@endforeach
<a class="ml-auto flex items-center gap-2 text-on-surface-variant hover:text-primary" href="{{ route('projects.index', array_filter(['status' => $status === 'all' ? null : $status, 'sort' => $nextSort, 'q' => $q])) }}" title="Change sort order">
<span class="font-label-sm text-label-sm whitespace-nowrap">Sort: {{ $sortLabels[$sort] }}</span>
<span class="material-symbols-outlined text-base">expand_more</span>
</a>
</div>
@if ($q)
<p class="font-body-sm text-body-sm text-on-surface-variant mb-6">Showing results for “{{ $q }}”. <a class="text-primary hover:underline" href="{{ route('projects.index') }}">Clear search</a></p>
@endif
<!-- Projects Bento Grid/List -->
<div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
@foreach ($projects as $project)
@include('projects._card', ['project' => $project])
@endforeach
<!-- Empty State / Add Placeholder -->
<a class="border-2 border-dashed border-outline-variant rounded-xl p-6 flex flex-col items-center justify-center text-center hover:bg-surface-container-low transition-colors cursor-pointer min-h-[220px]" href="{{ route('projects.new') }}">
<div class="w-12 h-12 rounded-full bg-surface-container-highest flex items-center justify-center mb-3">
<span class="material-symbols-outlined text-on-surface-variant">note_add</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-on-surface-variant">Start New Research</h3>
<p class="font-body-sm text-body-sm text-outline max-w-[200px] mt-1">Start a new project and upload the papers it will build on.</p>
</a>
</div>
<!-- Pagination/Footer Actions -->
<div class="mt-12 flex items-center justify-between border-t border-outline-variant pt-6">
<p class="font-body-sm text-body-sm text-on-surface-variant">Showing {{ $projects->count() }} of {{ $projects->total() }} {{ Str::plural('project', $projects->total()) }}</p>
@if ($projects->hasPages())
<div class="flex items-center gap-2">
<a class="w-10 h-10 border border-outline-variant rounded flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low transition-all {{ $projects->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" href="{{ $projects->previousPageUrl() ?? '#' }}">
<span class="material-symbols-outlined text-base">chevron_left</span>
</a>
@foreach ($projects->getUrlRange(max(1, $projects->currentPage() - 2), min($projects->lastPage(), $projects->currentPage() + 2)) as $page => $url)
@if ($page === $projects->currentPage())
<span class="px-4 py-2 bg-primary text-on-primary rounded font-label-sm text-label-sm">{{ $page }}</span>
@else
<a class="px-4 py-2 hover:bg-surface-container-low rounded font-label-sm text-label-sm" href="{{ $url }}">{{ $page }}</a>
@endif
@endforeach
<a class="w-10 h-10 border border-outline-variant rounded flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low transition-all {{ $projects->hasMorePages() ? '' : 'pointer-events-none opacity-40' }}" href="{{ $projects->nextPageUrl() ?? '#' }}">
<span class="material-symbols-outlined text-base">chevron_right</span>
</a>
</div>
@endif
</div>
@endif
</div>
@endsection
@push('scripts')
<script>
        // Micro-interaction for cards
        document.querySelectorAll('.group').forEach(card => {
            card.addEventListener('mouseenter', () => {
                const bar = card.querySelector('.bg-primary, .bg-on-tertiary-fixed-variant');
                if (bar) {
                    bar.style.filter = 'brightness(1.2)';
                }
            });
            card.addEventListener('mouseleave', () => {
                const bar = card.querySelector('.bg-primary, .bg-on-tertiary-fixed-variant');
                if (bar) {
                    bar.style.filter = 'none';
                }
            });
        });

        // Simple search feedback
        const searchInput = document.querySelector('input[type="text"]');
        searchInput.addEventListener('input', (e) => {
            if(e.target.value.length > 0) {
                searchInput.classList.add('bg-white', 'shadow-inner');
            } else {
                searchInput.classList.remove('bg-white', 'shadow-inner');
            }
        });
    </script>
@endpush
