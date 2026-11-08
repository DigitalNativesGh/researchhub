@extends('layouts.app')
@section('title', 'ResearchHub - Project Creation Wizard')
@section('body_class', 'bg-background text-on-background min-h-screen')
@section('main_class', 'ml-64 pt-16 min-h-screen')
@push('styles')
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .step-transition {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .source-card:hover {
            border-color: #041632;
        }
        .source-card.active {
            border-color: #041632;
            background-color: #c8dfff;
        }
    </style>
@endpush
@section('nav', 'projects')
@section('main')
<div class="max-w-[800px] mx-auto px-margin-desktop py-stack-lg">
<!-- Stepper Progress -->
<div class="flex items-center justify-between mb-12 relative">
<div class="absolute top-1/2 left-0 w-full h-[2px] bg-outline-variant -z-10 -translate-y-1/2"></div>
<a class="flex flex-col items-center gap-2 bg-background px-4" href="{{ route('projects.edit', $project) }}">
<div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm">1</div>
<span class="font-label-sm text-label-sm text-on-surface">{{ $project->degree_level->shortLabel() }}</span>
</a>
<div class="flex flex-col items-center gap-2 bg-background px-4">
<div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm">2</div>
<span class="font-label-sm text-label-sm text-on-surface">Payment</span>
</div>
<a class="flex flex-col items-center gap-2 bg-background px-4" href="{{ route('projects.edit', $project) }}">
<div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm">3</div>
<span class="font-label-sm text-label-sm text-on-surface">Project Info</span>
</a>
<div class="flex flex-col items-center gap-2 bg-background px-4">
<div class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm ring-4 ring-secondary-container">4</div>
<span class="font-label-md text-label-md text-primary">Sources</span>
</div>
<div class="flex flex-col items-center gap-2 bg-background px-4">
<div class="w-8 h-8 rounded-full bg-surface-container-highest text-on-surface-variant flex items-center justify-center font-bold text-sm">5</div>
<span class="font-label-sm text-label-sm text-on-surface-variant">Review</span>
</div>
</div>
<!-- Wizard Step Content -->
<section>
<div class="mb-10 text-center">
<h2 class="font-headline-lg text-headline-lg text-primary mb-3">Upload your sources</h2>
<p class="font-body-md text-body-md text-on-surface-variant max-w-2xl mx-auto">Add the research papers your thesis should build on, and any guidelines or templates from your institution. You can add or remove files until you submit.</p>
</div>
@include('partials.flash')
@foreach ([['source', 'Source papers', 'Peer-reviewed articles, reports or book chapters close to your topic.', $project->papers->where('kind', 'source')], ['guidelines', 'Institutional guidelines or templates (optional)', 'Your department’s thesis guide, a formatting template, or marking criteria.', $project->papers->where('kind', 'guidelines')]] as [$kind, $heading, $help, $files])
<div class="mb-10">
<h3 class="font-headline-sm text-headline-sm text-primary mb-1">{{ $heading }}</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-4">{{ $help }}</p>
<form action="{{ route('papers.store', $project) }}" class="border-2 border-dashed border-outline-variant rounded-xl p-8 flex flex-col items-center justify-center gap-3 bg-surface-container-lowest transition-colors hover:bg-surface-container-low group" data-dropzone data-max-kb="{{ $maxKb }}" enctype="multipart/form-data" method="POST">
@csrf
<input name="redirect" type="hidden" value="setup"/>
<input name="kind" type="hidden" value="{{ $kind }}"/>
<span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary">cloud_upload</span>
<p class="font-headline-sm text-headline-sm text-primary">Drag &amp; drop files</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">PDF, Word (.docx, .doc), RTF, OpenDocument or text · up to {{ $maxMb }} per file</p>
<label class="px-6 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 cursor-pointer">Browse Files
<input accept="{{ $formats }}" class="hidden" multiple name="papers[]" type="file"/>
</label>
</form>
@if ($files->isNotEmpty())
<div class="mt-4 space-y-2">
@foreach ($files as $paper)
<div class="flex items-center justify-between p-3 bg-surface border border-outline-variant rounded-lg">
<div class="flex items-center gap-3 min-w-0">
<span class="material-symbols-outlined text-on-surface-variant">description</span>
<span class="font-label-md text-label-md truncate">{{ $paper->original_name }}</span>
<span class="text-label-sm text-on-surface-variant whitespace-nowrap">{{ $paper->sizeForHumans() }}</span>
</div>
<form action="{{ route('papers.destroy', $paper) }}" method="POST">@csrf @method('DELETE')
<input name="redirect" type="hidden" value="setup"/>
<button class="text-error hover:bg-error-container p-2 rounded-full transition-colors" title="Remove" type="submit"><span class="material-symbols-outlined text-sm">delete</span></button>
</form>
</div>
@endforeach
</div>
@endif
</div>
@endforeach
@php $sourceCount = $project->papers->where('kind', 'source')->count(); @endphp
<div class="mt-12 flex justify-between items-center pt-8 border-t border-outline-variant">
<a class="flex items-center gap-2 px-6 py-3 text-primary font-label-md text-label-md hover:bg-surface-container transition-colors rounded-lg" href="{{ route('projects.edit', $project) }}"><span class="material-symbols-outlined">arrow_back</span>Back</a>
<div class="flex items-center gap-4">
@if ($sourceCount < $minSources)<span class="text-[12px] text-on-surface-variant">Add at least {{ $minSources }} source {{ Str::plural('paper', $minSources) }}</span>@endif
<a class="flex items-center gap-2 px-8 py-3 bg-primary text-on-primary font-label-md text-label-md hover:shadow-lg transition-all rounded-lg {{ $sourceCount < $minSources ? 'pointer-events-none opacity-50' : '' }}" href="{{ route('projects.setup.review', $project) }}">Next: Review<span class="material-symbols-outlined">arrow_forward</span></a>
</div>
</div>
</section>
<!-- Decorative Elements -->
<div class="mt-stack-lg grid grid-cols-3 gap-gutter opacity-40">
<div class="h-1 bg-gradient-to-r from-primary to-transparent rounded-full"></div>
<div class="h-1 bg-gradient-to-r from-primary to-transparent rounded-full"></div>
<div class="h-1 bg-gradient-to-r from-primary to-transparent rounded-full"></div>
</div>
</div>
@endsection
