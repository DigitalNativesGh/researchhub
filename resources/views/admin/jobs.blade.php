@extends('layouts.admin')
@section('page_title', 'Failed Jobs')
@section('nav', 'jobs')
@section('content')
<x-admin.header title="Failed Jobs" subtitle="Queue jobs that ran out of retries. Retry one after fixing its cause, or discard it.">
@if ($jobs->total())
<form action="{{ route('admin.jobs.flush') }}" method="POST" onsubmit="return confirm('Discard all failed jobs?')">@csrf @method('DELETE')
<button class="flex items-center gap-1 px-4 py-2 border border-error text-error rounded-lg font-label-md text-label-md hover:bg-error-container/40" type="submit"><span class="material-symbols-outlined text-[18px]">delete_sweep</span>Discard all</button>
</form>
@endif
</x-admin.header>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($jobs->isEmpty())
<div class="p-stack-md"><x-empty icon="task_alt" title="No failed jobs">Every queued job has either finished or is still retrying.</x-empty></div>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($jobs as $job)
<li class="px-stack-md py-4">
<div class="flex flex-col md:flex-row md:items-start justify-between gap-3">
<div class="min-w-0">
<p class="font-label-md text-label-md text-on-surface">{{ $job->name }} <span class="text-on-surface-variant font-normal">· {{ $job->queue }} · {{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</span></p>
<p class="font-body-sm text-body-sm text-error break-words mt-1">{{ Str::limit($job->summary, 300) }}</p>
<details class="mt-2">
<summary class="cursor-pointer font-label-sm text-label-sm text-on-surface-variant">Stack trace</summary>
<pre class="mt-2 p-3 bg-surface-container-low rounded text-[11px] overflow-x-auto max-h-64 custom-scrollbar">{{ Str::limit($job->exception, 6000) }}</pre>
</details>
</div>
<div class="flex gap-2 shrink-0">
<form action="{{ route('admin.jobs.retry', $job->uuid) }}" method="POST">@csrf
<button class="flex items-center gap-1 px-3 py-1.5 bg-primary text-on-primary rounded-lg font-label-sm text-label-sm hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[16px]">replay</span>Retry</button>
</form>
<form action="{{ route('admin.jobs.destroy', $job->uuid) }}" method="POST">@csrf @method('DELETE')
<button class="flex items-center gap-1 px-3 py-1.5 border border-outline-variant rounded-lg font-label-sm text-label-sm text-on-surface-variant hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[16px]">delete</span>Discard</button>
</form>
</div>
</div>
</li>
@endforeach
</ul>
<x-admin.pager :items="$jobs"/>
@endif
</section>
@endsection
