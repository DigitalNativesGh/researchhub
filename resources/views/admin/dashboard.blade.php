@extends('layouts.admin')
@section('page_title', 'Overview')
@section('nav', 'dashboard')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $maxDay = max(0.01, $days->max('cost'));
    $change = $cost['last'] > 0 ? round(($cost['month'] - $cost['last']) / $cost['last'] * 100) : null;
@endphp
@section('content')
<x-admin.header title="Platform Overview" subtitle="Accounts, projects and AI spend across ResearchHub.">
<span class="font-label-sm text-label-sm text-on-surface-variant">{{ now()->format('l, j F Y') }}</span>
</x-admin.header>

@if ($stalledJobs)
<x-alert icon="hourglass_disabled" kind="error" title="The queue worker is not running">{{ $stalledJobs }} {{ Str::plural('job', $stalledJobs) }} (data-file checks or emails) {{ $stalledJobs === 1 ? 'has' : 'have' }} been waiting more than 5 minutes. Start a worker: <code>php artisan queue:work</code> (or <code>composer dev</code> locally).</x-alert>
@endif
@if ($failedJobs)
<x-alert icon="warning" kind="error" title="Things need your attention"><a class="underline" href="{{ route('admin.jobs.index') }}">{{ $failedJobs }} failed queue {{ Str::plural('job', $failedJobs) }}</a></x-alert>
@endif

<div class="grid grid-cols-2 lg:grid-cols-5 gap-gutter">
<x-admin.stat :href="route('admin.payments.index')" icon="payments" label="Revenue this month" :value="\App\Models\Payment::format($revenue['month'])" :note="$revenue['count'].' '.Str::plural('payment', $revenue['count']).' · '.\App\Models\Payment::format($revenue['total']).' all time'"/>
<x-admin.stat :href="route('admin.users.index')" icon="group" label="Users" :value="number_format($users['total'])" :note="$users['new'].' new · '.$users['active'].' active in 30 days'"/>
<x-admin.stat :href="route('admin.queue')" icon="inbox" label="Work queue" :value="number_format($projects['queue'])" :note="$projects['total'].' projects · '.$projects['completed'].' completed'" />
<x-admin.stat :href="route('admin.usage')" icon="smart_toy" label="AI cost this month" :value="$money($cost['month'])" :note="$change === null ? 'Last month '.$money($cost['last']) : ($change >= 0 ? '+' : '').$change.'% vs last month ('.$money($cost['last']).')'"/>
<x-admin.stat :href="route('admin.usage')" icon="neurology" label="AI calls this month" :value="number_format($cost['calls'])" :note="$cost['errors'] ? $cost['errors'].' errored' : 'none errored'" :tone="$cost['errors'] ? 'error' : null"/>
</div>

<div class="grid grid-cols-12 gap-gutter">
<section class="col-span-12 lg:col-span-8 bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<div class="flex items-center justify-between mb-6">
<h3 class="font-headline-sm text-headline-sm text-primary">AI spend, last 30 days</h3>
<span class="font-label-sm text-label-sm text-on-surface-variant">{{ $money($days->sum('cost')) }} · {{ number_format($days->sum('calls')) }} calls</span>
</div>
<div class="flex items-end gap-1 h-40" role="img" aria-label="Daily AI cost for the last 30 days">
@foreach ($days as $d)
<div class="flex-1 h-full flex items-end group relative">
<div class="w-full bg-primary/80 group-hover:bg-primary rounded-t transition-colors" style="height: {{ $d['cost'] > 0 ? max(2, round($d['cost'] / $maxDay * 100)) : 0 }}%"></div>
<div class="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-inverse-surface text-inverse-on-surface rounded text-[11px] whitespace-nowrap z-10">{{ \Illuminate\Support\Carbon::parse($d['day'])->format('M j') }} · {{ $money($d['cost']) }} · {{ $d['calls'] }} calls</div>
</div>
@endforeach
</div>
<div class="flex justify-between mt-2 text-[11px] text-on-surface-variant">
<span>{{ \Illuminate\Support\Carbon::parse($days->first()['day'])->format('M j') }}</span><span>Today</span>
</div>
</section>

<section class="col-span-12 lg:col-span-4 bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-6">Projects by status</h3>
@php $statusRows = [['Waiting on the team', 'queue', 'approval'], ['Needs information / on hold', 'paused', 'pending'], ['Waiting on the researcher', 'researcher', 'progress'], ['Completed', 'completed', 'done']]; @endphp
<div class="space-y-3">
@foreach ($statusRows as [$label, $key, $badge])
<a class="flex items-center justify-between hover:bg-surface-container-low -mx-2 px-2 py-1 rounded" href="{{ route('admin.projects.index', ['status' => $key]) }}">
<x-badge :kind="$badge">{{ $label }}</x-badge>
<span class="font-label-md text-label-md text-primary tabular-nums">{{ $projects[$key] }}</span>
</a>
@endforeach
</div>
<h4 class="font-label-md text-label-md text-on-surface-variant mt-stack-md mb-3 uppercase tracking-wider text-[11px]">By research type</h4>
<div class="flex flex-wrap gap-2">
@forelse ($byLevel as $label => $count)
<span class="px-3 py-1 bg-surface-container-high rounded-full font-label-sm text-label-sm">{{ $label }} · {{ $count }}</span>
@empty
<span class="font-body-sm text-body-sm text-on-surface-variant">No projects yet.</span>
@endforelse
</div>
</section>
</div>

<div class="grid grid-cols-12 gap-gutter">
<section class="col-span-12 bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
<h3 class="font-headline-sm text-headline-sm text-primary">Longest waiting in the work queue</h3>
<a class="text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('admin.queue') }}">Open work queue</a>
</div>
@if ($queue->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">Nothing is waiting for editing or delivery.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($queue as $p)
<li><a class="flex items-center justify-between gap-4 px-stack-md py-3 hover:bg-surface-container-low" href="{{ route('admin.projects.show', $p) }}">
<div class="min-w-0"><p class="font-label-md text-label-md text-on-surface truncate">{{ $p->title }}</p><p class="text-[12px] text-on-surface-variant">{{ $p->reference() }} · {{ $p->degree_level->shortLabel() }} · {{ $p->user?->name }}</p></div>
<div class="text-right shrink-0"><x-badge :kind="$p->workflow_status->badge()">{{ $p->workflow_status->label() }}</x-badge><p class="text-[12px] text-on-surface-variant mt-1">waiting {{ $p->workflow_changed_at?->diffForHumans(null, true) ?? '—' }}</p></div>
</a></li>
@endforeach
</ul>
@endif
</section>

<section class="col-span-12 lg:col-span-6 bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
<h3 class="font-headline-sm text-headline-sm text-primary">Newest users</h3>
<a class="text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('admin.users.index') }}">All users</a>
</div>
<ul class="divide-y divide-outline-variant">
@foreach ($recentUsers as $u)
<li><a class="flex items-center gap-3 px-stack-md py-3 hover:bg-surface-container-low" href="{{ route('admin.users.show', $u) }}">
<x-avatar :user="$u" class="w-8 h-8 text-[10px]"/>
<div class="flex-1 min-w-0">
<p class="font-label-md text-label-md text-on-surface truncate">{{ $u->name }} @if ($u->is_admin)<span class="ml-1 text-[10px] font-bold uppercase text-secondary">Admin</span>@endif</p>
<p class="text-[12px] text-on-surface-variant truncate">{{ $u->email }}</p>
</div>
<span class="text-[12px] text-on-surface-variant whitespace-nowrap">{{ $u->projects_count }} {{ Str::plural('project', $u->projects_count) }} · {{ $u->created_at->diffForHumans() }}</span>
</a></li>
@endforeach
</ul>
</section>

<section class="col-span-12 lg:col-span-6 bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
<h3 class="font-headline-sm text-headline-sm text-primary">Due soon</h3>
<a class="text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('admin.queue', ['deadline' => '30', 'scope' => 'all']) }}">View all</a>
</div>
@if ($dueSoon->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">No active project is due in the next 14 days.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($dueSoon as $p)
@php $daysLeft = now()->startOfDay()->diffInDays($p->deadline, false); @endphp
<li><a class="flex items-center justify-between gap-4 px-stack-md py-3 hover:bg-surface-container-low" href="{{ route('admin.projects.show', $p) }}">
<div class="min-w-0"><p class="font-label-md text-label-md text-on-surface truncate">{{ $p->title }}</p><p class="text-[12px] text-on-surface-variant">{{ $p->reference() }} · {{ $p->workflow_status->label() }}</p></div>
<span class="text-[12px] whitespace-nowrap {{ $daysLeft < 0 ? 'text-error font-semibold' : 'text-on-surface' }}">{{ $p->deadline->format('j M') }} · {{ $daysLeft < 0 ? abs($daysLeft).'d overdue' : $daysLeft.'d left' }}</span>
</a></li>
@endforeach
</ul>
@endif
</section>
</div>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
<h3 class="font-headline-sm text-headline-sm text-primary">Recent administrator actions</h3>
<a class="text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('admin.activity') }}">Activity log</a>
</div>
@if ($log->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">No administrator actions recorded yet.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($log as $entry)
<li class="px-stack-md py-3 flex items-center justify-between gap-4">
<p class="font-body-sm text-body-sm text-on-surface">{{ $entry->summary }}</p>
<span class="text-[12px] text-on-surface-variant whitespace-nowrap">{{ $entry->admin?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</span>
</li>
@endforeach
</ul>
@endif
</section>
@endsection
