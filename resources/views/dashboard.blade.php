@extends('layouts.app')
@section('title', 'ResearchHub Dashboard')
@section('body_class', 'bg-background text-on-surface')
@section('main_class', 'ml-64 pt-24 pb-12 px-10')
@push('styles')
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        /* Custom scrollbar for minimalist UI */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #E9ECEF; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #D1D5DB; }
    </style>
@endpush
@section('nav', 'dashboard')
@section('main')
<div class="max-w-7xl mx-auto">
@include('partials.flash')
<!-- Hero Welcome Section -->
<section class="mb-10 flex flex-col md:flex-row justify-between items-end gap-6">
<div class="animate-fade-in">
<h2 class="font-headline-lg text-headline-lg text-primary mb-2">{{ $greeting }}, {{ $user->firstName() }}.</h2>
<p class="font-body-lg text-body-lg text-on-surface-variant">Ready for your next discovery? @if ($attention->count() > 0)You have {{ $attention->count() }} {{ Str::plural('project', $attention->count()) }} requiring attention today.@elseif ($projects->isEmpty())Start your first project to begin.@else All your projects are moving forward.@endif</p>
</div>
<a class="flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-opacity-90 shadow-lg shadow-primary/10 transition-all transform hover:-translate-y-0.5" href="{{ route('projects.new') }}">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">add</span>
                    Start New Research
                </a>
</section>
<div class="grid grid-cols-12 gap-8">
<!-- Ongoing Research (Bento Grid Style) -->
<div class="col-span-12 lg:col-span-8">
<div class="flex items-center justify-between mb-6">
<h3 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2">
<span class="material-symbols-outlined text-secondary">clinical_notes</span>
                            Ongoing Research
                        </h3>
<a class="text-primary font-label-md text-label-md hover:underline decoration-2 underline-offset-4" href="{{ route('projects.index') }}">View All Projects</a>
</div>
@if ($projects->isEmpty())
<x-empty action="Create Your First Project" :href="route('projects.new')" icon="note_add" title="No projects yet">Start a project, upload the research articles you want to build on, and we will take you from Chapters 1–3 to your complete, edited thesis.</x-empty>
@else
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
@foreach ($cards as $project)
<!-- Project Card {{ $loop->iteration }} -->
<div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow flex flex-col h-full group">
<div class="flex justify-between items-start mb-4">
<span class="{{ $project->workflow_status->needsResearcher() ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-tertiary-fixed text-on-tertiary-fixed' }} px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">{{ $project->workflow_status->researcherLabel() }}</span>
</div>
<h4 class="font-headline-sm text-headline-sm text-primary mb-2 group-hover:text-on-secondary-fixed-variant transition-colors">{{ $project->title }}</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-6 flex-grow">{{ Str::limit($project->workflow_status->researcherMessage(), 140) }}</p>
<div class="flex items-center justify-between mt-auto">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-secondary text-sm">category</span>
<span class="text-label-sm font-label-sm text-on-surface-variant">{{ $project->degree_level->label() }}</span>
</div>
<a class="font-label-md text-label-md text-primary flex items-center gap-1 group-hover:gap-2 transition-all" href="{{ route('projects.show', $project) }}">
                                    View Details
                                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
</a>
</div>
</div>
@endforeach
@if ($featured)
<!-- Project Card (Featured Wide) -->
<div class="md:col-span-2 bg-primary-container text-on-primary-fixed rounded-xl p-8 relative overflow-hidden group shadow-xl">
<div class="absolute -right-16 -bottom-16 w-64 h-64 bg-primary rounded-full opacity-20 blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
<div class="relative z-10">
<div class="flex justify-between items-start mb-6">
<div class="flex items-center gap-3">
<div class="p-2 bg-on-primary-container/20 rounded-lg">
<span class="material-symbols-outlined text-on-primary-fixed">science</span>
</div>
<div>
<p class="text-[10px] font-bold uppercase tracking-widest text-primary-fixed">Active Project</p>
<h4 class="font-headline-md text-headline-md text-white">{{ $featured->title }}</h4>
</div>
</div>
<span class="bg-on-tertiary-container text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">{{ $featured->workflow_status->needsResearcher() ? 'Needs You' : $featured->workflow_status->researcherLabel() }}</span>
</div>
<p class="font-body-md text-body-md text-primary-fixed/80 max-w-2xl mb-8">
                                    {{ $featured->workflow_status->researcherMessage() }} {{ $featured->metaLine() }}.
                                </p>
<div class="flex items-center gap-6">
<a class="bg-white text-primary px-6 py-2 rounded-lg font-label-md text-label-md hover:bg-primary-fixed transition-colors" href="{{ route('projects.show', $featured) }}">
                                        Open Workspace
                                    </a>
<div class="flex -space-x-3">
<div class="w-8 h-8 rounded-full border-2 border-primary-container bg-gray-200 overflow-hidden flex items-center justify-center text-[10px] font-bold text-primary" title="{{ $featured->papers_count }} papers">{{ $featured->papers_count }}</div>
<div class="w-8 h-8 rounded-full border-2 border-primary-container bg-on-primary-container/30 flex items-center justify-center text-[10px] font-bold text-white" title="Overall progress">
                                            {{ $featured->progressPercent() }}%
                                        </div>
</div>
</div>
</div>
</div>
@endif
</div>
@endif
</div>
<!-- Sidebar Content: Recent Updates & Attention -->
<div class="col-span-12 lg:col-span-4 space-y-8">
<!-- Recent Updates -->
<div class="bg-surface border border-outline-variant rounded-xl p-6">
<h3 class="font-headline-sm text-headline-sm text-on-surface mb-6 flex items-center gap-2">
<span class="material-symbols-outlined text-secondary">update</span>
                            Recent Updates
                        </h3>
<div class="space-y-6">
@forelse ($events as $event)
<a class="flex gap-4 relative group" href="{{ route('notifications.open', $event->id) }}">
@unless ($loop->last)
<div class="absolute left-[11px] top-7 bottom-0 w-[2px] bg-outline-variant opacity-30"></div>
@endunless
<div class="w-6 h-6 rounded-full {{ ['bg-primary-fixed', 'bg-secondary-fixed', 'bg-tertiary-fixed'][$loop->index % 3] }} flex-shrink-0 flex items-center justify-center z-10">
<span class="material-symbols-outlined text-[14px] text-primary" style="font-variation-settings: 'FILL' 1;">{{ $event->data['icon'] ?? 'notifications' }}</span>
</div>
<div>
<p class="font-label-md text-label-md text-on-surface group-hover:text-primary {{ $event->read_at ? '' : 'font-bold' }}">{{ $event->data['title'] ?? 'Update' }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ Str::limit($event->data['message'] ?? '', 110) }}</p>
<span class="text-[10px] text-on-surface-variant opacity-60">{{ $event->created_at->diffForHumans() }}</span>
</div>
</a>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Updates about your projects will appear here.</p>
@endforelse
</div>
<a class="block text-center w-full mt-6 py-2 border border-outline-variant rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low transition-colors" href="{{ route('notifications.index') }}">
                            View All Updates
                        </a>
</div>
<!-- Needs Your Attention -->
<div class="bg-surface border border-outline-variant rounded-xl p-6">
<div class="flex items-center justify-between mb-6">
<h3 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2">
<span class="material-symbols-outlined text-secondary">mail</span>
                                Needs Your Attention
                            </h3>
@if ($attention->count())
<span class="bg-error text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold">{{ $attention->count() }}</span>
@endif
</div>
<div class="space-y-4">
@forelse ($attention->take(3) as $project)
<a class="flex items-center gap-3 p-3 hover:bg-surface-container-low rounded-lg transition-colors cursor-pointer group" href="{{ route('projects.show', $project) }}">
<div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
<span class="material-symbols-outlined text-primary">{{ match ($project->workflow_status) { \App\Enums\WorkflowStatus::Draft => 'upload_file', \App\Enums\WorkflowStatus::NeedsInfo => 'help', default => 'upload' } }}</span>
</div>
<div class="flex-grow overflow-hidden">
<div class="flex justify-between items-center">
<p class="font-label-md text-label-md text-on-surface truncate">{{ $project->title }}</p>
<span class="text-[10px] text-on-surface-variant whitespace-nowrap ml-2">{{ ($project->workflow_changed_at ?? $project->updated_at)->format('M j') }}</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant truncate group-hover:text-primary">{{ match ($project->workflow_status) { \App\Enums\WorkflowStatus::Draft => 'Finish setting up', \App\Enums\WorkflowStatus::NeedsInfo => 'Reply to our team', default => 'Collect and upload your data' } }}</p>
</div>
</a>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Nothing is waiting for you right now.</p>
@endforelse
</div>
<a class="block text-center w-full mt-6 py-2 bg-secondary-container text-on-secondary-container rounded-lg font-label-md text-label-md hover:brightness-95 transition-all" href="{{ route('projects.index', ['status' => 'review']) }}">
                            Open My Projects
                        </a>
</div>
<!-- Quick Action Card -->
<a class="bg-gradient-to-br from-tertiary-fixed to-secondary-fixed rounded-xl p-6 border border-outline-variant/30 flex items-center justify-between group cursor-pointer hover:shadow-lg transition-all" href="{{ $nextStep['href'] }}">
<div>
<p class="font-label-md text-label-md text-on-tertiary-fixed font-bold">Next Step</p>
<p class="font-body-sm text-body-sm text-on-tertiary-fixed/80">{{ $nextStep['text'] }}</p>
</div>
<div class="w-12 h-12 bg-white/40 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-on-tertiary-fixed">{{ $nextStep['icon'] }}</span>
</div>
</a>
</div>
</div>
</div>
@endsection
@push('scripts')
<script>
        // Micro-interaction for search bar focus
        const searchInput = document.querySelector('input[type="text"]');
        searchInput.addEventListener('focus', () => {
            searchInput.parentElement.classList.add('scale-[1.02]');
        });
        searchInput.addEventListener('blur', () => {
            searchInput.parentElement.classList.remove('scale-[1.02]');
        });

        // Simple animation trigger on load
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.animate-fade-in').forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease-out';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
@endpush
