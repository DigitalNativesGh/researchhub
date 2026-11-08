@extends('layouts.admin')
@section('page_title', 'Activity Log')
@section('nav', 'activity')
@section('content')
<x-admin.header title="Activity Log" subtitle="Changes made by administrators, and the pipeline activity of every project."/>

<div class="flex gap-2">
@foreach (['admin' => 'Administrator actions', 'pipeline' => 'Project pipeline'] as $key => $label)
<a class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors {{ $tab === $key ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}" href="{{ route('admin.activity', ['tab' => $key]) }}">{{ $label }}</a>
@endforeach
</div>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($entries->isEmpty())
<div class="p-stack-md"><x-empty icon="history" title="Nothing recorded yet">Entries appear here as they happen.</x-empty></div>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($entries as $entry)
<li class="px-stack-md py-3 flex items-start justify-between gap-4">
@if ($tab === 'admin')
<div class="min-w-0">
<p class="font-body-sm text-body-sm text-on-surface">{{ $entry->summary }}</p>
<p class="text-[12px] text-on-surface-variant">{{ $entry->admin?->name ?? 'Deleted administrator' }} · {{ $entry->action }} @if ($entry->ip_address)· {{ $entry->ip_address }}@endif</p>
@if ($entry->details)
<p class="text-[12px] text-on-surface-variant break-words">@foreach ($entry->details as $k => $v){{ $k }}: {{ is_array($v) ? json_encode($v) : $v }}@if (! $loop->last); @endif @endforeach</p>
@endif
</div>
@else
<div class="flex gap-3 min-w-0">
<span class="material-symbols-outlined text-[18px] {{ $entry->type === 'failed' ? 'text-error' : 'text-on-surface-variant' }}">{{ $entry->icon }}</span>
<div class="min-w-0">
<p class="font-label-md text-label-md text-on-surface">{{ $entry->title }}</p>
@if ($entry->message)<p class="text-[12px] text-on-surface-variant break-words">{{ Str::limit($entry->message, 200) }}</p>@endif
@if ($entry->project)<a class="text-[12px] text-secondary hover:underline" href="{{ route('admin.projects.show', $entry->project) }}">{{ Str::limit($entry->project->title, 70) }}</a> <span class="text-[12px] text-on-surface-variant">· {{ $entry->project->user?->name }}</span>@endif
</div>
</div>
@endif
<span class="text-[12px] text-on-surface-variant whitespace-nowrap" title="{{ $entry->created_at }}">{{ $entry->created_at->diffForHumans() }}</span>
</li>
@endforeach
</ul>
<x-admin.pager :items="$entries"/>
@endif
</section>
@endsection
