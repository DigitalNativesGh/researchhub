@extends('layouts.admin')
@section('page_title', 'AI Usage')
@section('nav', 'usage')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $short = fn ($n) => $n >= 1_000_000 ? round($n / 1_000_000, 1).'M' : ($n >= 1_000 ? round($n / 1_000).'K' : (string) $n);
    $input = (int) $totals->input;
@endphp
@section('content')
<form action="{{ route('admin.usage') }}" method="GET">
<x-admin.header title="AI Usage & Cost" subtitle="Claude calls across every account. Costs are estimates; your Anthropic invoice is authoritative.">
<select aria-label="Period" class="px-4 py-2 border border-outline-variant rounded-lg bg-surface-container-low font-body-md text-body-md" name="period" onchange="this.form.submit()">
@foreach ($periods as $value => $label)
<option @selected($period === $value) value="{{ $value }}">{{ $label }}</option>
@endforeach
</select>
<a class="flex items-center gap-1 px-4 py-2 border border-outline-variant rounded-lg text-primary font-label-md text-label-md hover:bg-surface-container-low" href="{{ route('admin.usage.export', ['period' => $period]) }}"><span class="material-symbols-outlined text-[18px]">download</span>Export CSV</a>
</x-admin.header>
</form>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-gutter">
<x-admin.stat label="Estimated cost" :value="$money($totals->cost)" :note="$periods[$period]"/>
<x-admin.stat label="AI calls" :value="number_format($totals->calls)" :note="($totals->errors ?: 'none').' errored'" :tone="$totals->errors ? 'error' : null"/>
<x-admin.stat label="Input tokens" :value="$short($input)" :note="($input ? round($totals->cached / $input * 100) : 0).'% from cache'"/>
<x-admin.stat label="Output tokens" :value="$short((int) $totals->output)"/>
</div>

<div class="grid grid-cols-12 gap-gutter">
@foreach ([['By stage', $byStage], ['By model', $byModel]] as [$heading, $rows])
@php $max = max(0.000001, (float) $rows->max('cost')); @endphp
<section class="col-span-12 md:col-span-6 lg:col-span-4 bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl self-start">
<h3 class="font-headline-sm text-headline-sm text-primary mb-6">{{ $heading }}</h3>
@forelse ($rows as $row)
<div class="mb-4">
<div class="flex justify-between mb-1 gap-2"><p class="font-label-md text-label-md text-on-surface truncate">{{ ucfirst(str_replace('_', ' ', Str::after($row->name, 'claude-'))) }}</p><p class="font-label-md text-label-md text-primary whitespace-nowrap">{{ $money($row->cost) }} <span class="text-on-surface-variant font-normal">· {{ $row->calls }}</span></p></div>
<div class="w-full bg-surface-container-high rounded-full h-1.5"><div class="bg-primary h-1.5 rounded-full" style="width: {{ max(1, round($row->cost / $max * 100)) }}%"></div></div>
</div>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">No calls in this period.</p>
@endforelse
</section>
@endforeach
<section class="col-span-12 lg:col-span-4 bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl self-start">
<h3 class="font-headline-sm text-headline-sm text-primary mb-6">Top users by cost</h3>
@forelse ($topUsers as $row)
<a class="flex items-center justify-between gap-3 py-2 hover:bg-surface-container-low -mx-2 px-2 rounded" href="{{ $row->user ? route('admin.users.show', $row->user) : '#' }}">
<div class="min-w-0"><p class="font-label-md text-label-md text-on-surface truncate">{{ $row->user?->name ?? 'Deleted user' }}</p><p class="text-[12px] text-on-surface-variant truncate">{{ $row->user?->email }}</p></div>
<p class="font-label-md text-label-md text-primary whitespace-nowrap">{{ $money($row->cost) }}</p>
</a>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">No calls in this period.</p>
@endforelse
</section>
</div>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low"><h3 class="font-headline-sm text-headline-sm text-primary">Recent calls</h3></div>
@if ($recent->isEmpty())
<div class="p-stack-md"><x-empty icon="monitoring" title="No calls yet">Calls appear here as soon as Claude works on any project.</x-empty></div>
@else
<div class="overflow-x-auto custom-scrollbar">
<table class="w-full text-left min-w-[860px]">
<thead><tr class="bg-surface-bright border-b border-outline-variant">
@foreach (['Time', 'User', 'Stage', 'Model', 'Tokens in / out', 'Cost', 'Status'] as $h)
<th class="py-3 px-4 font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant whitespace-nowrap">{{ $h }}</th>
@endforeach
</tr></thead>
<tbody class="divide-y divide-outline-variant font-body-sm text-body-sm">
@foreach ($recent as $call)
<tr class="hover:bg-surface-container-low">
<td class="py-3 px-4 text-on-surface-variant whitespace-nowrap">{{ $call->created_at->format('M j, H:i') }}</td>
<td class="py-3 px-4">{{ $call->user?->name ?? '—' }}</td>
<td class="py-3 px-4">{{ $call->label ?: ucfirst(str_replace('_', ' ', $call->stage)) }}<p class="text-[12px] text-on-surface-variant">{{ $call->project ? Str::limit($call->project->title, 40) : 'Title assistant' }}</p></td>
<td class="py-3 px-4"><span class="px-2 py-1 bg-surface-container-high text-secondary rounded font-label-sm text-label-sm whitespace-nowrap">{{ Str::after($call->model, 'claude-') }}</span></td>
<td class="py-3 px-4 tabular-nums text-[12px] text-on-surface-variant whitespace-nowrap">{{ number_format($call->input_tokens + $call->cached_tokens + $call->cache_write_tokens) }} / {{ number_format($call->output_tokens) }}</td>
<td class="py-3 px-4 tabular-nums">{{ $money($call->cost) }}</td>
<td class="py-3 px-4">@if ($call->status === 'success')<span class="material-symbols-outlined text-[18px] text-on-tertiary-container" title="Succeeded">check_circle</span>@else<span class="material-symbols-outlined text-[18px] text-error" title="{{ Str::limit($call->error, 200) }}">report</span>@endif</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<x-admin.pager :items="$recent"/>
@endif
</section>
@endsection
