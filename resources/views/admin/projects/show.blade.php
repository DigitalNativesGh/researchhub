@extends('layouts.admin')
@section('page_title', $project->reference())
@section('nav', 'queue')
@use('App\Enums\WorkflowStatus', 'W')
@php
    $w = $project->workflow_status;
    $field = 'w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-low font-body-sm text-body-sm';
    $btn = 'flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-label-md text-label-md whitespace-nowrap';
    $daysLeft = $project->deadline ? now()->startOfDay()->diffInDays($project->deadline, false) : null;
    $stagePanel = [
        1 => ['title' => 'Stage 1 · Chapters 1–3 and instrument(s)', 'start' => W::Submitted, 'working' => W::Stage1InProgress, 'types' => ['chapters_1_3' => 'Chapters 1–3', 'instrument' => 'Data collection instrument'], 'open' => in_array($w, [W::Submitted, W::Stage1InProgress], true) || $latest[1]->isNotEmpty()],
        3 => ['title' => 'Stage 3 · Complete thesis', 'start' => W::DataSubmitted, 'working' => W::Stage3InProgress, 'types' => ['complete_thesis' => 'Complete thesis (Chapters 1–5, references, appendices)'], 'open' => in_array($w, [W::DataSubmitted, W::Stage3InProgress, W::Completed], true) || $latest[3]->isNotEmpty()],
    ];
@endphp
@section('content')
<a class="inline-flex items-center gap-1 text-on-surface-variant hover:text-primary font-label-md text-label-md" href="{{ route('admin.queue') }}"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Work queue</a>

<div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
<div class="max-w-3xl">
<p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">{{ $project->reference() }} · {{ $project->metaLine() }} · {{ $project->discipline }}</p>
<h2 class="font-headline-lg text-headline-lg text-primary mt-1">{{ $project->title }}</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 flex items-center gap-1">
@if (str_starts_with((string) $project->title_source, 'suggested'))<span class="material-symbols-outlined text-[16px]">lightbulb</span>@endif{{ $project->titleSourceLabel() }}
</p>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">
Researcher: @if ($project->user)<a class="text-primary hover:underline" href="{{ route('admin.users.show', $project->user) }}">{{ $project->user->name }}</a> ({{ $project->user->email }})@else a deleted user @endif
</p>
</div>
<div class="text-right">
<x-badge :kind="$w->badge()">{{ $w->label() }}</x-badge>
<p class="text-[12px] text-on-surface-variant mt-2">for {{ $project->workflow_changed_at?->diffForHumans(null, true) ?? '—' }}</p>
@if ($daysLeft !== null)
<p class="text-[12px] mt-1 {{ $daysLeft < 0 ? 'text-error font-semibold' : ($daysLeft <= 14 ? 'text-on-surface font-semibold' : 'text-on-surface-variant') }}">Deadline {{ $project->deadline->format('j M Y') }} · {{ $daysLeft < 0 ? abs($daysLeft).' days overdue' : $daysLeft.' days left' }}</p>
@endif
</div>
</div>

@if ($w === W::NeedsInfo)
<x-alert icon="help" kind="warn" title="Waiting for the researcher">{{ $project->info_request }}</x-alert>
@elseif ($w === W::OnHold)
<x-alert icon="pause_circle" kind="warn" title="On hold">Resumes to “{{ W::from($project->resume_status)->label() }}”.</x-alert>
@endif

<div class="grid grid-cols-12 gap-gutter">
<div class="col-span-12 lg:col-span-8 space-y-gutter">

<!-- Project details -->
<section class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md">
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">Project details</h3>
<dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 font-body-sm text-body-sm">
@foreach ([
    'Degree level' => $project->degree_level->label(),
    'Research approach' => $project->research_type->label(),
    'Field of study' => $project->discipline,
    'Institution' => $project->institution ?: '—',
    'Citation style' => $project->citation_style->label(),
    'Required length' => $project->word_count_target ? number_format($project->word_count_target).' words' : 'Not given (typical '.$project->degree_level->typicalRange().')',
    'Deadline' => $project->deadline?->format('j F Y') ?? 'Not given',
    'Submitted' => $project->submitted_at?->format('j M Y, H:i') ?? 'Not yet',
] as $label => $value)
<div><dt class="text-on-surface-variant">{{ $label }}</dt><dd class="text-on-surface">{{ $value }}</dd></div>
@endforeach
</dl>
@foreach (['Description / objectives' => $project->description, 'Institutional guidelines' => $project->guidelines, 'Notes for the team' => $project->writer_notes] as $label => $text)
@if (filled($text))
<div class="mt-4"><p class="text-on-surface-variant font-body-sm text-body-sm">{{ $label }}</p><p class="font-body-sm text-body-sm text-on-surface whitespace-pre-line">{{ $text }}</p></div>
@endif
@endforeach
</section>

<!-- Sources -->
<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between gap-4">
<h3 class="font-headline-sm text-headline-sm text-primary">Sources ({{ $sources->count() }}){{ $guidelineFiles->isNotEmpty() ? ' and guidelines ('.$guidelineFiles->count().')' : '' }}</h3>
@if ($sources->isNotEmpty() || $guidelineFiles->isNotEmpty())
<a class="{{ $btn }} border border-outline-variant text-primary hover:bg-surface-container" href="{{ route('admin.workflow.sources', $project) }}"><span class="material-symbols-outlined text-[18px]">folder_zip</span>Download all (ZIP)</a>
@endif
</div>
@if ($sources->isEmpty() && $guidelineFiles->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">No files uploaded yet.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($sources->concat($guidelineFiles) as $paper)
<li class="px-stack-md py-2 flex items-center justify-between gap-4">
<div class="min-w-0"><p class="font-body-sm text-body-sm text-on-surface truncate">{{ $paper->original_name }}</p><p class="text-[12px] text-on-surface-variant">{{ $paper->isGuidelines() ? 'Guidelines / template' : 'Source' }} · {{ $paper->sizeForHumans() }}</p></div>
<a class="p-2 text-on-surface-variant hover:text-primary" href="{{ route('admin.workflow.paper', [$project, $paper]) }}" title="Download"><span class="material-symbols-outlined text-[20px]">download</span></a>
</li>
@endforeach
</ul>
@endif
</section>

@foreach ($stagePanel as $stage => $panel)
@continue(! $panel['open'] && ! ($stage === 3 && $datasets->whereNotNull('submitted_at')->isNotEmpty()))
@php
    $docs = $latest[$stage];
    $undelivered = $docs->whereNull('delivered_at');
    $delivered = $docs->whereNotNull('delivered_at')->isNotEmpty();
    $active = in_array($w, [$panel['start'], $panel['working']], true);
@endphp
<section class="bg-surface-container-lowest border {{ $active ? 'border-primary' : 'border-outline-variant' }} rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between gap-4">
<h3 class="font-headline-sm text-headline-sm text-primary">{{ $panel['title'] }}</h3>
@if ($active)<x-badge kind="approval">Needs action</x-badge>@elseif ($delivered)<x-badge kind="done">Delivered</x-badge>@endif
</div>
<div class="p-stack-md space-y-stack-md">

@if ($stage === 3)
@php $submitted = $datasets->whereNotNull('submitted_at')->whereNull('superseded_at'); @endphp
<div>
<div class="flex items-center justify-between gap-4 mb-2">
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">Researcher’s data ({{ $submitted->count() }})</p>
@if ($submitted->isNotEmpty())<a class="{{ $btn }} border border-outline-variant text-primary hover:bg-surface-container" href="{{ route('admin.workflow.data', $project) }}"><span class="material-symbols-outlined text-[18px]">folder_zip</span>Download all (ZIP)</a>@endif
</div>
<ul class="divide-y divide-outline-variant border border-outline-variant rounded-lg">
@forelse ($submitted as $dataset)
<li class="px-4 py-2 flex items-center justify-between gap-4">
<div class="min-w-0"><p class="font-body-sm text-body-sm text-on-surface truncate">{{ $dataset->original_name }}</p><p class="text-[12px] text-on-surface-variant">{{ ucfirst(str_replace('_', ' ', $dataset->kind)) }} · {{ $dataset->sizeForHumans() }}{{ $dataset->row_count ? ' · '.$dataset->row_count.' rows' : '' }}</p></div>
<a class="p-2 text-on-surface-variant hover:text-primary" href="{{ route('admin.workflow.dataset', [$project, $dataset]) }}" title="Download"><span class="material-symbols-outlined text-[20px]">download</span></a>
</li>
@empty
<li class="px-4 py-3 font-body-sm text-body-sm text-on-surface-variant">No data submitted yet.</li>
@endforelse
</ul>
</div>
@endif

@if ($w === $panel['start'])
<form action="{{ route('admin.workflow.in-progress', $project) }}" class="space-y-3" method="POST">@csrf
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">Mark as in progress <span class="normal-case font-normal">(details are internal only)</span></p>
<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
<input class="{{ $field }}" maxlength="120" name="writer_name" placeholder="Assigned writer" type="text"/>
<input class="{{ $field }}" min="{{ now()->toDateString() }}" name="expected_at" title="Expected completion date" type="date"/>
<button class="{{ $btn }} bg-primary text-on-primary hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[18px]">play_arrow</span>Mark as in progress</button>
</div>
<input class="{{ $field }}" maxlength="2000" name="notes" placeholder="Internal notes (optional)" type="text"/>
<x-field-error name="expected_at"/>
</form>
@elseif ($w === $panel['working'] && $assignment)
<p class="font-body-sm text-body-sm text-on-surface"><span class="text-on-surface-variant">In progress:</span> {{ $assignment->editor_name ?: 'writer not recorded' }}@if ($assignment->expected_return_at) · expected {{ $assignment->expected_return_at->format('j M Y') }}@if ($assignment->isOverdue()) <span class="text-error font-semibold">(overdue)</span>@endif @endif · since {{ $assignment->created_at->diffForHumans() }}@if ($assignment->notes)<br><span class="text-on-surface-variant">{{ $assignment->notes }}</span>@endif</p>
@endif

@if ($w !== W::Draft && ($active || $delivered || in_array($w, [W::NeedsInfo, W::OnHold], true)))
<form action="{{ route('admin.workflow.upload', $project) }}" class="space-y-3" enctype="multipart/form-data" method="POST">@csrf
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">Upload {{ $delivered ? 'a corrected version (kept as a new version; deliver again to release it)' : 'the documents' }}</p>
<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
<select class="{{ $field }}" name="type" onchange="this.form.querySelector('[data-instrument]').classList.toggle('hidden', this.value !== 'instrument')">
@foreach ($panel['types'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
</select>
<input accept="{{ $stage === 1 ? '.docx,.doc,.pdf,.xlsx' : '.docx,.doc,.pdf' }}" class="{{ $field }} md:col-span-2" name="file" required type="file"/>
</div>
@if ($stage === 1)
<div class="hidden" data-instrument>
<input class="{{ $field }}" list="instrument-labels-{{ $project->id }}" maxlength="120" name="instrument" placeholder="Instrument name, e.g. Questionnaire, Interview guide, Observation checklist" type="text"/>
<datalist id="instrument-labels-{{ $project->id }}">@foreach (array_unique(array_merge($instrumentLabels, ['Questionnaire', 'Interview guide', 'Observation checklist'])) as $label)<option value="{{ $label }}"></option>@endforeach</datalist>
</div>
@endif
<div class="flex flex-col md:flex-row gap-3">
<input class="{{ $field }}" maxlength="2000" name="notes" placeholder="Note about this version (optional, internal)" type="text"/>
<button class="{{ $btn }} bg-primary text-on-primary hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[18px]">upload</span>Upload</button>
</div>
<p class="text-[12px] text-on-surface-variant">Word (.docx, .doc) or PDF{{ $stage === 1 ? '; instruments may also be Excel (.xlsx)' : '' }}.</p>
<x-field-error name="file"/>
</form>
@endif

@if ($docs->isNotEmpty())
<div>
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-2">Latest versions</p>
<ul class="divide-y divide-outline-variant border border-outline-variant rounded-lg">
@foreach ($docs as $doc)
<li class="px-4 py-2 flex items-center justify-between gap-4">
<div class="min-w-0"><p class="font-label-md text-label-md text-on-surface">{{ $doc->label }} <span class="font-normal text-on-surface-variant">v{{ $doc->version }} · .{{ $doc->format }} · {{ $doc->sizeLabel() }}</span></p><p class="text-[12px] text-on-surface-variant">Uploaded by {{ $doc->creator?->name ?? '—' }} {{ $doc->created_at->diffForHumans() }}</p></div>
<div class="flex items-center gap-2 shrink-0">
@if ($doc->delivered_at)<x-badge kind="done">Delivered {{ $doc->delivered_at->format('j M') }}</x-badge>@else<x-badge kind="approval">Not delivered</x-badge>@endif
<a class="p-2 text-on-surface-variant hover:text-primary" href="{{ route('admin.workflow.download', [$project, $doc]) }}" title="Download"><span class="material-symbols-outlined text-[18px]">download</span></a>
</div>
</li>
@endforeach
</ul>
@if ($undelivered->isNotEmpty())
<form action="{{ route('admin.workflow.deliver', $project) }}" class="mt-3 flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 bg-secondary-container/30 border border-secondary-container rounded-lg" method="POST">@csrf
<input name="stage" type="hidden" value="{{ $stage }}"/>
<label class="flex items-start gap-2 font-body-sm text-body-sm text-on-surface"><input class="mt-1 rounded border-outline-variant text-primary" name="confirm" required type="checkbox" value="1"/>
<span>I have checked {{ $undelivered->count() === 1 ? 'this file' : 'these files' }}. Release {{ $undelivered->count() === 1 ? 'it' : 'them' }} to the researcher and notify them.</span></label>
<button class="{{ $btn }} bg-primary text-on-primary hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[18px]">send</span>{{ $delivered ? 'Deliver correction' : 'Deliver to researcher' }}</button>
</form>
<x-field-error name="confirm"/>
@endif
</div>
@endif
</div>
</section>
@endforeach

<!-- Version history -->
<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low"><h3 class="font-headline-sm text-headline-sm text-primary">Version history</h3></div>
@if ($history->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">No documents uploaded yet.</p>
@else
<div class="overflow-x-auto custom-scrollbar">
<table class="w-full text-left font-body-sm text-body-sm min-w-[600px]">
<thead><tr class="border-b border-outline-variant">@foreach (['Document', 'Version', 'Uploaded', 'Status', ''] as $h)<th class="py-2 px-4 font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant">{{ $h }}</th>@endforeach</tr></thead>
<tbody class="divide-y divide-outline-variant">
@foreach ($history as $doc)
<tr>
<td class="py-2 px-4">{{ $doc->label }} <span class="text-on-surface-variant">.{{ $doc->format }}</span></td>
<td class="py-2 px-4 tabular-nums">v{{ $doc->version }}</td>
<td class="py-2 px-4 text-on-surface-variant whitespace-nowrap">{{ $doc->created_at->format('j M Y H:i') }}{{ $doc->creator ? ' · '.$doc->creator->name : '' }}</td>
<td class="py-2 px-4">{{ $doc->delivered_at ? 'Delivered '.$doc->delivered_at->format('j M') : 'Not delivered' }}</td>
<td class="py-2 px-4"><a class="text-secondary hover:underline" href="{{ route('admin.workflow.download', [$project, $doc]) }}">Download</a></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</section>

<!-- Audit trail -->
<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low"><h3 class="font-headline-sm text-headline-sm text-primary">Audit trail</h3></div>
@if ($audit->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">Nothing recorded yet.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($audit as $entry)
<li class="px-stack-md py-2 flex items-start justify-between gap-4"><p class="font-body-sm text-body-sm text-on-surface">{{ $entry->summary }}</p><span class="text-[12px] text-on-surface-variant whitespace-nowrap">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('j M, H:i') }}</span></li>
@endforeach
</ul>
@endif
</section>
</div>

<aside class="col-span-12 lg:col-span-4 space-y-gutter">
@if ($w->needsAdmin())
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-2">Ask the researcher</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-3">Pauses the project and {{ $w->stage() === 3 ? 'lets them add data files' : 'reopens their sources' }} until they reply.</p>
<form action="{{ route('admin.workflow.request-info', $project) }}" class="space-y-2" method="POST">@csrf
<textarea class="{{ $field }}" maxlength="2000" name="message" placeholder="What do you need from them?" required rows="3"></textarea>
<x-field-error name="message"/>
<button class="{{ $btn }} w-full border border-outline-variant text-primary hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[18px]">help</span>Request information</button>
</form>
</section>
@endif

<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
@if (in_array($w, [W::OnHold, W::NeedsInfo], true))
<form action="{{ route('admin.workflow.resume', $project) }}" method="POST">@csrf
<button class="{{ $btn }} w-full bg-primary text-on-primary hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[18px]">play_arrow</span>{{ $w === W::NeedsInfo ? 'Withdraw request and resume' : 'Resume project' }}</button>
</form>
@elseif ($w->isActive())
<form action="{{ route('admin.workflow.hold', $project) }}" class="space-y-2" method="POST">@csrf
<input class="{{ $field }}" maxlength="500" name="reason" placeholder="Reason (internal, optional)" type="text"/>
<button class="{{ $btn }} w-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[18px]">pause</span>Put on hold</button>
</form>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $w === W::Draft ? 'The researcher has not submitted this project yet.' : 'This project is completed.' }}</p>
@endif
</section>

<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-4">Status history</h3>
<ol class="space-y-3">
@forelse ($transitions as $t)
<li class="font-body-sm text-body-sm">
<p class="text-on-surface font-semibold">{{ $t->toLabel() }}</p>
<p class="text-[12px] text-on-surface-variant">{{ $t->created_at->format('j M Y H:i') }} · {{ $t->user?->name ?? 'System' }}</p>
@if ($t->note)<p class="text-[12px] text-on-surface-variant break-words">{{ Str::limit($t->note, 200) }}</p>@endif
</li>
@empty
<li class="font-body-sm text-body-sm text-on-surface-variant">No changes yet.</li>
@endforelse
</ol>
</section>

@if ($assignments->isNotEmpty())
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-3">Writers (internal)</h3>
<ul class="space-y-2 font-body-sm text-body-sm">
@foreach ($assignments as $a)
<li><span class="text-on-surface">Stage {{ $a->stage }}: {{ $a->editor_name ?: '—' }}</span><span class="block text-[12px] text-on-surface-variant">{{ $a->created_at->format('j M Y') }}{{ $a->expected_return_at ? ' · expected '.$a->expected_return_at->format('j M') : '' }}{{ $a->completed_at ? ' · delivered '.$a->completed_at->format('j M') : '' }}</span></li>
@endforeach
</ul>
</section>
@endif

<section class="bg-surface-container-lowest border border-error/40 p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-error mb-2">Delete project</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-3">Removes the project, its uploads, data and delivered documents.</p>
<form action="{{ route('admin.projects.destroy', $project) }}" class="space-y-2" method="POST">@csrf @method('DELETE')
<input autocomplete="off" class="{{ $field }}" name="confirm" placeholder="Type {{ $project->reference() }} to confirm" required type="text"/>
<button class="w-full px-4 py-2.5 bg-error text-on-error rounded-lg font-label-md text-label-md hover:bg-error/90" type="submit">Delete project permanently</button>
</form>
</section>
</aside>
</div>
@endsection
