@extends('layouts.project')
@section('title', 'ResearchHub | Sources')
@section('stage_key', 'sources')
@php $isDraft = $project->workflow_status === \App\Enums\WorkflowStatus::Draft; @endphp
@section('stage')
<div class="grid grid-cols-12 gap-gutter">
<section class="col-span-12 lg:col-span-8 bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<div class="mb-6">
<h3 class="font-headline-sm text-headline-sm text-primary">Sources and guidelines</h3>
<p class="text-label-sm text-on-surface-variant mt-1">{{ $editable ? 'Upload the papers your thesis should build on, and any guidelines or templates from your institution.' : 'Your sources are locked now that the project is submitted. Contact us if something needs to change.' }}</p>
</div>
@foreach ([['source', 'Source papers', $papers], ['guidelines', 'Guidelines and templates', $guidelineFiles]] as [$kind, $heading, $files])
<div class="mb-stack-md">
<h4 class="font-label-md text-label-md text-primary mb-3">{{ $heading }}</h4>
@if ($editable)
<form action="{{ route('papers.store', $project) }}" class="border-2 border-dashed border-outline-variant rounded-xl p-6 flex flex-col items-center justify-center gap-3 bg-surface-container-lowest transition-colors hover:bg-surface-container-low group mb-3" data-dropzone data-max-kb="{{ $maxKb }}" enctype="multipart/form-data" method="POST">
@csrf
<input name="kind" type="hidden" value="{{ $kind }}"/>
<span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary">cloud_upload</span>
<p class="font-headline-sm text-headline-sm text-primary">Drag &amp; drop files</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">PDF, Word (.docx, .doc), RTF, OpenDocument or text · up to {{ $maxMb }} per file</p>
<label class="px-6 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 cursor-pointer">Browse Files
<input accept="{{ $formats }}" class="hidden" multiple name="papers[]" type="file"/>
</label>
</form>
@endif
<div class="space-y-2">
@forelse ($files as $paper)
<div class="flex items-center justify-between gap-4 p-3 border border-outline-variant rounded-lg">
<div class="flex items-center gap-3 min-w-0">
<span class="material-symbols-outlined text-on-surface-variant">description</span>
<div class="min-w-0"><p class="font-label-md text-label-md text-on-surface truncate">{{ $paper->original_name }}</p><p class="text-label-sm text-on-surface-variant">{{ $paper->sizeForHumans() }}</p></div>
</div>
<div class="flex items-center gap-1 shrink-0">
<a class="p-2 text-on-surface-variant hover:text-primary" href="{{ route('papers.file', $paper) }}" title="Download"><span class="material-symbols-outlined text-sm">download</span></a>
@if ($editable)
<form action="{{ route('papers.destroy', $paper) }}" method="POST" onsubmit="return confirm('Remove {{ addslashes($paper->original_name) }}?')">@csrf @method('DELETE')<button class="p-2 text-on-surface-variant hover:text-error" title="Remove" type="submit"><span class="material-symbols-outlined text-sm">delete</span></button></form>
@endif
</div>
</div>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">None uploaded.</p>
@endforelse
</div>
</div>
@endforeach
</section>
<aside class="col-span-12 lg:col-span-4 space-y-stack-md">
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">Source papers</p>
<p class="font-headline-lg text-headline-lg text-primary mt-2">{{ $papers->count() }}</p>
@if ($isDraft)
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">At least {{ $minSources }} needed to submit.</p>
<a class="mt-4 flex items-center justify-center gap-2 bg-primary text-on-primary px-6 py-3 rounded-lg hover:bg-primary/90 font-label-md text-label-md {{ $papers->count() < $minSources ? 'opacity-50 pointer-events-none' : '' }}" href="{{ route('projects.setup.review', $project) }}"><span class="material-symbols-outlined">send</span>Review &amp; Submit</a>
@elseif ($editable)
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Our team asked for more information. When you have finished, send your reply from the Overview page.</p>
@endif
</section>
</aside>
</div>
@endsection
