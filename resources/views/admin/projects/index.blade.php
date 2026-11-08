@extends('layouts.admin')
@section('page_title', 'Projects')
@section('nav', 'projects')
@section('content')
<x-admin.header title="Projects" subtitle="Every research project on the platform, with its owner, stage and AI spend."/>

<form action="{{ route('admin.projects.index') }}" class="flex flex-col md:flex-row md:items-center justify-between gap-4" method="GET">
<div class="flex flex-wrap gap-2">
@foreach ($filters as $key => $label)
<a class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors {{ $status === $key ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}" href="{{ route('admin.projects.index', array_filter(['status' => $key === 'all' ? null : $key, 'level' => $level?->value, 'q' => $q])) }}">{{ $label }}</a>
@endforeach
</div>
<input name="status" type="hidden" value="{{ $status === 'all' ? '' : $status }}"/>
<div class="flex items-center gap-3">
<select aria-label="Research type" class="px-4 py-2 border border-outline-variant rounded-lg bg-surface-container-low font-body-md text-body-md" name="level" onchange="this.form.submit()">
<option value="">All research types</option>
@foreach (\App\Enums\DegreeLevel::cases() as $l)
<option @selected($level === $l) value="{{ $l->value }}">{{ $l->shortLabel() }}</option>
@endforeach
</select>
<div class="relative w-full md:w-72">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
<input class="w-full bg-surface-container-low border border-outline-variant rounded-lg pl-10 pr-4 py-2 font-body-md text-body-md focus:ring-2 focus:ring-primary/10 focus:border-primary" name="q" placeholder="Title or owner" type="search" value="{{ $q }}"/>
</div>
</div>
</form>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($projects->isEmpty())
<div class="p-stack-md"><x-empty icon="folder_off" title="No projects found">Try a different search or filter.</x-empty></div>
@else
<div class="overflow-x-auto custom-scrollbar">
<table class="w-full text-left min-w-[860px]">
<thead><tr class="bg-surface-container-low border-b border-outline-variant">
@foreach (['Project', 'Owner', 'Type', 'Papers', 'AI cost', 'Updated', 'Status'] as $h)
<th class="py-3 px-4 font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant whitespace-nowrap">{{ $h }}</th>
@endforeach
</tr></thead>
<tbody class="divide-y divide-outline-variant font-body-sm text-body-sm">
@foreach ($projects as $p)
<tr class="hover:bg-surface-container-low transition-colors">
<td class="py-3 px-4 max-w-[320px]">
<a class="font-label-md text-label-md text-primary hover:underline line-clamp-2" href="{{ route('admin.projects.show', $p) }}">{{ $p->title }}</a>
<p class="text-[12px] text-on-surface-variant">{{ $p->reference() }} · {{ $p->discipline }}</p>
</td>
<td class="py-3 px-4">@if ($p->user)<a class="hover:underline" href="{{ route('admin.users.show', $p->user) }}">{{ $p->user->name }}</a><p class="text-[12px] text-on-surface-variant">{{ $p->user->email }}</p>@endif</td>
<td class="py-3 px-4 whitespace-nowrap">{{ $p->degree_level->shortLabel() }}<p class="text-[12px] text-on-surface-variant">{{ $p->research_type->label() }}</p></td>
<td class="py-3 px-4 tabular-nums">{{ $p->papers_count }}</td>
<td class="py-3 px-4 tabular-nums">${{ number_format((float) $p->ai_cost, 2) }}</td>
<td class="py-3 px-4 text-on-surface-variant whitespace-nowrap">{{ $p->updated_at->diffForHumans() }}</td>
<td class="py-3 px-4"><x-badge :kind="$p->workflow_status->badge()">{{ $p->workflow_status->label() }}</x-badge></td>
</tr>
@endforeach
</tbody>
</table>
</div>
<x-admin.pager :items="$projects"/>
@endif
</section>
@endsection
