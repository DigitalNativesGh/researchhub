@extends('layouts.admin')
@section('page_title', 'Work Queue')
@section('nav', 'queue')
@php $field = 'px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-low font-body-sm text-body-sm'; @endphp
@section('content')
<x-admin.header title="Work Queue" subtitle="Projects waiting on the team, longest waiting first. Download the sources or data, write the documents, then upload and deliver them."/>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-gutter">
@foreach ($cards as $s)
<a class="block bg-surface-container-lowest border {{ $filters['status'] === $s ? 'border-primary' : 'border-outline-variant' }} p-4 rounded-xl hover:border-primary transition-colors" href="{{ route('admin.queue', ['status' => $s->value]) }}">
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">{{ $s->label() }}</p>
<p class="font-headline-md text-headline-md mt-1 text-primary">{{ $counts[$s->value] ?? 0 }}</p>
</a>
@endforeach
</div>

<form action="{{ route('admin.queue') }}" class="flex flex-wrap items-end gap-3 bg-surface-container-lowest border border-outline-variant p-4 rounded-xl" method="GET">
<label class="flex flex-col gap-1 text-[12px] text-on-surface-variant">Show
<select class="{{ $field }}" name="scope">
<option value="team" @selected($scope === 'team')>Waiting on the team</option>
<option value="all" @selected($scope === 'all')>Also waiting on researchers / on hold</option>
</select>
</label>
<label class="flex flex-col gap-1 text-[12px] text-on-surface-variant">Status
<select class="{{ $field }}" name="status">
<option value="">Any</option>
@foreach ($cards as $s)<option @selected($filters['status'] === $s) value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
</select>
</label>
<label class="flex flex-col gap-1 text-[12px] text-on-surface-variant">Stage
<select class="{{ $field }}" name="stage">
<option value="">Any</option>
<option @selected($filters['stage'] === 1) value="1">Stage 1 · Chapters 1–3</option>
<option @selected($filters['stage'] === 3) value="3">Stage 3 · Complete thesis</option>
</select>
</label>
<label class="flex flex-col gap-1 text-[12px] text-on-surface-variant">Degree level
<select class="{{ $field }}" name="level">
<option value="">Any</option>
@foreach (\App\Enums\DegreeLevel::cases() as $l)<option @selected($filters['level'] === $l) value="{{ $l->value }}">{{ $l->shortLabel() }}</option>@endforeach
</select>
</label>
<label class="flex flex-col gap-1 text-[12px] text-on-surface-variant">Discipline
<select class="{{ $field }}" name="discipline">
<option value="">Any</option>
@foreach ($disciplines as $d)<option @selected($filters['discipline'] === $d)>{{ $d }}</option>@endforeach
</select>
</label>
<label class="flex flex-col gap-1 text-[12px] text-on-surface-variant">Deadline
<select class="{{ $field }}" name="deadline">
<option value="">Any</option>
@foreach ($deadlines as $value => $label)<option @selected($filters['deadline'] === $value) value="{{ $value }}">{{ $label }}</option>@endforeach
</select>
</label>
<button class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md" type="submit">Filter</button>
<a class="px-4 py-2 text-on-surface-variant font-label-md text-label-md hover:text-primary" href="{{ route('admin.queue') }}">Reset</a>
</form>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($projects->isEmpty())
<div class="p-stack-md"><x-empty icon="inbox" title="Nothing waiting">No project matches these filters.</x-empty></div>
@else
<div class="overflow-x-auto custom-scrollbar">
<table class="w-full text-left min-w-[960px]">
<thead><tr class="bg-surface-container-low border-b border-outline-variant">
@foreach (['Project', 'Researcher', 'Level', 'Status', 'Waiting', 'Deadline', 'Writer', ''] as $h)
<th class="py-3 px-4 font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant whitespace-nowrap">{{ $h }}</th>
@endforeach
</tr></thead>
<tbody class="divide-y divide-outline-variant font-body-sm text-body-sm">
@foreach ($projects as $p)
@php
    $assignment = $p->editingAssignments->first();
    $hours = $p->workflow_changed_at ? $p->workflow_changed_at->diffInHours(now()) : 0;
    $daysLeft = $p->deadline ? now()->startOfDay()->diffInDays($p->deadline, false) : null;
@endphp
<tr class="hover:bg-surface-container-low transition-colors">
<td class="py-3 px-4 max-w-[300px]">
<a class="font-label-md text-label-md text-primary hover:underline line-clamp-2" href="{{ route('admin.projects.show', $p) }}">{{ $p->title }}</a>
<p class="text-[12px] text-on-surface-variant">{{ $p->reference() }} · {{ $p->discipline }}</p>
</td>
<td class="py-3 px-4">{{ $p->user?->name ?? '—' }}<p class="text-[12px] text-on-surface-variant">{{ $p->user?->email }}</p></td>
<td class="py-3 px-4 whitespace-nowrap">{{ $p->degree_level->shortLabel() }}</td>
<td class="py-3 px-4"><x-badge :kind="$p->workflow_status->badge()">{{ $p->workflow_status->label() }}</x-badge></td>
<td class="py-3 px-4 whitespace-nowrap {{ $hours >= 72 ? 'text-error font-semibold' : ($hours >= 24 ? 'text-on-surface' : 'text-on-surface-variant') }}" title="{{ $p->workflow_changed_at?->format('j M Y H:i') }}">{{ $p->workflow_changed_at?->diffForHumans(null, true) ?? '—' }}</td>
<td class="py-3 px-4 whitespace-nowrap">@if ($daysLeft === null)<span class="text-on-surface-variant">—</span>@else<span class="{{ $daysLeft < 0 ? 'text-error font-semibold' : ($daysLeft <= 7 ? 'text-on-surface font-semibold' : 'text-on-surface-variant') }}">{{ $p->deadline->format('j M') }}</span><p class="text-[12px] {{ $daysLeft < 0 ? 'text-error' : 'text-on-surface-variant' }}">{{ $daysLeft < 0 ? abs($daysLeft).' days overdue' : ($daysLeft === 0 ? 'today' : $daysLeft.' days left') }}</p>@endif</td>
<td class="py-3 px-4">@if ($assignment){{ $assignment->editor_name ?: '—' }}@if ($assignment->expected_return_at)<p class="text-[12px] {{ $assignment->isOverdue() ? 'text-error' : 'text-on-surface-variant' }}">expected {{ $assignment->expected_return_at->format('j M') }}</p>@endif @else<span class="text-on-surface-variant">—</span>@endif</td>
<td class="py-3 px-4"><a class="flex items-center gap-1 px-3 py-1.5 bg-primary text-on-primary rounded-lg font-label-sm text-label-sm hover:bg-primary/90 whitespace-nowrap" href="{{ route('admin.projects.show', $p) }}">Open<span class="material-symbols-outlined text-[16px]">arrow_forward</span></a></td>
</tr>
@endforeach
</tbody>
</table>
</div>
<x-admin.pager :items="$projects"/>
@endif
</section>
@endsection
