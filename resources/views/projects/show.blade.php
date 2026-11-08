@extends('layouts.project')
@section('title', 'ResearchHub | '.$project->reference())
@section('stage_key', 'overview')
@php
    $workflow = $project->workflow_status;
    $stage1 = $deliverables->where('stage', 1);
    $stage3 = $deliverables->where('stage', 3);
    $icons = ['chapters_1_3' => 'menu_book', 'instrument' => 'assignment', 'complete_thesis' => 'workspace_premium'];
@endphp
@section('stage')
<div class="grid grid-cols-12 gap-gutter">
<div class="col-span-12 lg:col-span-8 space-y-stack-md">
@if ($workflow === \App\Enums\WorkflowStatus::NeedsInfo)
@php $dataStage = \App\Enums\WorkflowStatus::tryFrom((string) $project->resume_status)?->stage() === 3; @endphp
<section class="bg-surface-container-lowest border-2 border-primary p-stack-md rounded-xl">
<div class="flex items-center gap-2 mb-2"><span class="material-symbols-outlined text-primary">help</span><h3 class="font-headline-sm text-headline-sm text-primary">Our team needs some information</h3></div>
<p class="font-body-md text-body-md text-on-surface whitespace-pre-line mb-4">{{ $project->info_request }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-4">If you need to, <a class="text-secondary hover:underline" href="{{ $dataStage ? route('projects.data', $project) : route('projects.sources', $project) }}">{{ $dataStage ? 'add data files' : 'add or remove sources' }}</a> first, then send your reply.</p>
<form action="{{ route('projects.respond', $project) }}" class="space-y-3" method="POST">@csrf
<textarea class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-low focus:ring-2 focus:ring-primary/10 focus:border-primary" maxlength="3000" name="reply" placeholder="Your reply" rows="3"></textarea>
<button class="flex items-center gap-2 bg-primary text-on-primary px-6 py-2.5 rounded-lg font-label-md text-label-md hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[18px]">send</span>Send reply</button>
</form>
</section>
@endif

<!-- Progress tracker -->
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-6">Your progress</h3>
<ol class="relative space-y-6">
@foreach ($stages as $i => $s)
<li class="flex gap-4">
<div class="flex flex-col items-center">
@if ($s['state'] === 'done')
<span class="w-9 h-9 rounded-full bg-primary text-on-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">check</span></span>
@elseif ($s['state'] === 'current')
<span class="w-9 h-9 rounded-full border-2 border-primary bg-surface flex items-center justify-center shrink-0"><span class="w-3 h-3 bg-primary rounded-full {{ $workflow->needsResearcher() ? '' : 'animate-pulse' }}"></span></span>
@else
<span class="w-9 h-9 rounded-full border-2 border-outline-variant bg-surface text-on-surface-variant flex items-center justify-center shrink-0 font-bold text-[13px]">{{ $i }}</span>
@endif
@unless ($loop->last)<span class="w-0.5 flex-1 min-h-6 mt-1 {{ $s['state'] === 'done' ? 'bg-primary' : 'bg-surface-container-high' }}"></span>@endunless
</div>
<div class="pb-2 min-w-0">
<p class="font-label-md text-label-md {{ $s['state'] === 'pending' ? 'text-on-surface-variant' : 'text-primary' }}">{{ $s['title'] }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $s['detail'] }}</p>
@if ($s['state'] === 'current')
<p class="mt-2 font-body-sm text-body-sm text-on-surface bg-secondary-container/40 border border-secondary-container rounded-lg px-3 py-2">{{ $workflow->researcherMessage() }}</p>
@elseif ($s['date'])
<p class="text-[12px] text-on-surface-variant mt-1">{{ $s['state'] === 'done' ? 'Done' : '' }} {{ $s['date'] }}</p>
@endif
</div>
</li>
@endforeach
</ol>
</section>

<!-- Delivered documents (edited versions only) -->
<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low">
<h3 class="font-headline-sm text-headline-sm text-primary">Your documents</h3>
</div>
@if ($deliverables->isEmpty())
<div class="p-stack-md">
<x-empty icon="description" title="Nothing to download yet">{{ $workflow === \App\Enums\WorkflowStatus::Draft ? 'Your documents appear here once you have submitted your project and our team has prepared them.' : 'Your Chapters 1–3 and data collection instrument will appear here as soon as our team has finished them. We will email you.' }}</x-empty>
</div>
@else
@foreach ([[3, 'Final thesis', $stage3], [1, 'Stage 1 · Chapters 1–3 and instrument', $stage1]] as [$n, $heading, $docs])
@if ($docs->isNotEmpty())
<div class="px-stack-md pt-4 pb-1"><p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">{{ $heading }}</p></div>
<ul class="divide-y divide-outline-variant">
@foreach ($docs as $doc)
<li class="px-stack-md py-3 flex items-center justify-between gap-4">
<div class="flex items-center gap-3 min-w-0">
<span class="w-10 h-10 rounded-lg bg-primary/5 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-primary">{{ $icons[$doc->type->value] ?? 'description' }}</span></span>
<div class="min-w-0">
<p class="font-label-md text-label-md text-on-surface truncate">{{ $doc->label }}</p>
<p class="text-[12px] text-on-surface-variant">{{ strtoupper($doc->format) }} · {{ $doc->sizeLabel() }} · {{ $doc->version > 1 ? 'Updated' : 'Delivered' }} {{ $doc->delivered_at->format('M j, Y') }}</p>
</div>
</div>
<a class="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 whitespace-nowrap" href="{{ route('deliverables.download', [$project, $doc]) }}"><span class="material-symbols-outlined text-[18px]">download</span>Download</a>
</li>
@endforeach
</ul>
@endif
@endforeach
@endif
</section>
</div>

<aside class="col-span-12 lg:col-span-4 space-y-stack-md">
<!-- Next step -->
<section class="bg-primary text-on-primary p-stack-md rounded-xl">
<div class="flex items-center gap-2 mb-3">
<span class="material-symbols-outlined">{{ $nextStep['icon'] }}</span>
<h3 class="font-label-md text-label-md uppercase tracking-wider font-bold">What happens next</h3>
</div>
<p class="font-headline-sm text-headline-sm mb-2">{{ $nextStep['title'] }}</p>
<p class="font-body-sm text-body-sm opacity-90 mb-4">{{ $nextStep['text'] }}</p>
@if ($nextStep['button'])
<a class="inline-flex items-center gap-2 bg-on-primary text-primary px-5 py-2.5 rounded-lg font-label-md text-label-md hover:opacity-90" href="{{ $nextStep['href'] }}">{{ $nextStep['button'] }}<span class="material-symbols-outlined text-[18px]">arrow_forward</span></a>
@endif
</section>

<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl space-y-3">
<h3 class="font-label-md text-label-md text-primary">Project details</h3>
<dl class="font-body-sm text-body-sm space-y-2">
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Research type</dt><dd class="text-on-surface text-right">{{ $project->degree_level->label() }}</dd></div>
@if ($project->institution)
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Institution</dt><dd class="text-on-surface text-right">{{ $project->institution }}</dd></div>
@endif
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Approach</dt><dd class="text-on-surface text-right">{{ $project->research_type->label() }}</dd></div>
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Field</dt><dd class="text-on-surface text-right">{{ $project->discipline }}</dd></div>
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Citation style</dt><dd class="text-on-surface text-right">{{ $project->citation_style->label() }}</dd></div>
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Sources</dt><dd class="text-on-surface text-right"><a class="text-secondary hover:underline" href="{{ route('projects.sources', $project) }}">{{ $project->papers_count }} {{ Str::plural('file', $project->papers_count) }}</a></dd></div>
@if ($project->deadline)
<div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Deadline</dt><dd class="text-on-surface text-right">{{ $project->deadline->format('M j, Y') }}</dd></div>
@endif
</dl>
@if ($workflow === \App\Enums\WorkflowStatus::Draft)
<a class="flex items-center gap-1 text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('projects.edit', $project) }}"><span class="material-symbols-outlined text-[16px]">edit</span>Edit details</a>
@endif
</section>

@if (in_array($workflow, [\App\Enums\WorkflowStatus::Draft, \App\Enums\WorkflowStatus::Completed], true))
<form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Delete this project and all of its files? This cannot be undone.')">
@csrf @method('DELETE')
<button class="w-full flex items-center justify-center gap-2 px-4 py-2.5 border border-outline-variant text-error rounded-lg font-label-md text-label-md hover:bg-error-container/30" type="submit"><span class="material-symbols-outlined text-[18px]">delete</span>Delete project</button>
</form>
@endif
</aside>
</div>
@endsection
