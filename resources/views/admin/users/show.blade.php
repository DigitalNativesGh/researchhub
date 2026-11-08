@extends('layouts.admin')
@section('page_title', $user->name)
@section('nav', 'users')
@php $self = $user->is(auth()->user()); $money = fn ($v) => '$'.number_format((float) $v, 2); @endphp
@section('content')
<a class="inline-flex items-center gap-1 text-on-surface-variant hover:text-primary font-label-md text-label-md" href="{{ route('admin.users.index') }}"><span class="material-symbols-outlined text-[18px]">arrow_back</span>All users</a>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
<div class="flex items-center gap-4">
<x-avatar :user="$user" class="w-14 h-14 text-[16px]"/>
<div>
<h2 class="font-headline-lg text-headline-lg text-primary">{{ $user->name }}</h2>
<p class="font-body-md text-body-md text-on-surface-variant">{{ $user->email }} · {{ $user->roleLabel() }}@if ($user->institution) · {{ $user->institution }}@endif</p>
</div>
</div>
<div class="flex flex-wrap gap-2">
@if ($user->is_admin)<x-badge kind="approval">Administrator</x-badge>@endif
@if ($user->isSuspended())<x-badge kind="failed">Suspended</x-badge>@elseif (! $user->hasVerifiedEmail())<x-badge kind="pending">Unverified</x-badge>@else<x-badge kind="done">Active</x-badge>@endif
</div>
</div>

@if ($user->isSuspended())
<x-alert icon="block" kind="error" title="Suspended {{ $user->suspended_at->diffForHumans() }}">{{ $user->suspension_reason ?: 'No reason recorded.' }}</x-alert>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-gutter">
<x-admin.stat label="Projects" :value="$user->projects_count"/>
<x-admin.stat label="AI cost (all time)" :value="$money($usage['cost'])" :note="$money($usage['month']).' this month'"/>
<x-admin.stat label="AI calls" :value="number_format($usage['calls'])" :note="$usage['errors'] ? $usage['errors'].' errored' : 'none errored'"/>
<x-admin.stat label="Last sign-in" :value="$user->last_login_at?->diffForHumans() ?? 'Never'" :note="'Joined '.$user->created_at->format('M j, Y')"/>
</div>

<div class="grid grid-cols-12 gap-gutter">
<section class="col-span-12 lg:col-span-8 bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden self-start">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low"><h3 class="font-headline-sm text-headline-sm text-primary">Projects</h3></div>
@if ($projects->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">This user has no projects.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($projects as $p)
<li><a class="flex items-center justify-between gap-4 px-stack-md py-3 hover:bg-surface-container-low" href="{{ route('admin.projects.show', $p) }}">
<div class="min-w-0">
<p class="font-label-md text-label-md text-on-surface truncate">{{ $p->title }}</p>
<p class="text-[12px] text-on-surface-variant">{{ $p->reference() }} · {{ $p->metaLine() }} · {{ $p->papers_count }} {{ Str::plural('paper', $p->papers_count) }}</p>
</div>
<x-badge :kind="$p->workflow_status->badge()">{{ $p->workflow_status->label() }}</x-badge>
</a></li>
@endforeach
</ul>
@endif
</section>

<section class="col-span-12 lg:col-span-8 bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden self-start lg:order-last">
<div class="px-stack-md py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
<h3 class="font-headline-sm text-headline-sm text-primary">Payments</h3>
<a class="text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('admin.payments.index', ['q' => $user->email]) }}">All payments</a>
</div>
@if ($payments->isEmpty())
<p class="px-stack-md py-6 font-body-sm text-body-sm text-on-surface-variant">No payments yet.</p>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($payments as $payment)
<li class="px-stack-md py-3 flex items-center justify-between gap-4">
<div class="min-w-0">
<p class="font-label-md text-label-md text-on-surface">{{ $payment->displayAmount() }} · {{ $payment->degree_level->shortLabel() }}</p>
<p class="text-[12px] text-on-surface-variant truncate">{{ $payment->reference }} · {{ $payment->methodLabel() }} · {{ $payment->created_at->format('M j, Y') }} · {{ $payment->project ? Str::limit($payment->project->title, 40) : ($payment->consumed_at ? 'Project deleted' : ($payment->isSuccessful() ? 'Unused credit' : '')) }}</p>
</div>
@include('admin.payments._status', ['payment' => $payment])
</li>
@endforeach
</ul>
@endif
</section>

<aside class="col-span-12 lg:col-span-4 space-y-gutter">
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl space-y-3">
<h3 class="font-headline-sm text-headline-sm text-primary mb-2">Manage account</h3>
@if ($self)
<p class="font-body-sm text-body-sm text-on-surface-variant">This is your own account. Another administrator has to change its access.</p>
@else
<form action="{{ route('admin.users.admin', $user) }}" method="POST">@csrf
<button class="w-full flex items-center gap-2 px-4 py-2.5 border border-outline-variant rounded-lg font-label-md text-label-md text-primary hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>{{ $user->is_admin ? 'Revoke administrator access' : 'Make administrator' }}</button>
</form>
@endif
@unless ($user->hasVerifiedEmail())
<form action="{{ route('admin.users.verify', $user) }}" method="POST">@csrf
<button class="w-full flex items-center gap-2 px-4 py-2.5 border border-outline-variant rounded-lg font-label-md text-label-md text-primary hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[18px]">verified</span>Mark email as verified</button>
</form>
@endunless
<form action="{{ route('admin.users.password-reset', $user) }}" method="POST">@csrf
<button class="w-full flex items-center gap-2 px-4 py-2.5 border border-outline-variant rounded-lg font-label-md text-label-md text-primary hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[18px]">lock_reset</span>Send password reset link</button>
</form>
@unless ($self)
@if ($user->isSuspended())
<form action="{{ route('admin.users.unsuspend', $user) }}" method="POST">@csrf
<button class="w-full flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90" type="submit"><span class="material-symbols-outlined text-[18px]">lock_open</span>Restore access</button>
</form>
@else
<form action="{{ route('admin.users.suspend', $user) }}" class="space-y-2 pt-2 border-t border-outline-variant" method="POST">@csrf
<label class="block font-label-sm text-label-sm text-on-surface-variant" for="reason">Suspend this account</label>
<input class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-low font-body-sm text-body-sm" id="reason" maxlength="255" name="reason" placeholder="Reason (optional, shown here only)" type="text"/>
<button class="w-full flex items-center gap-2 px-4 py-2.5 border border-error text-error rounded-lg font-label-md text-label-md hover:bg-error-container/40" type="submit"><span class="material-symbols-outlined text-[18px]">block</span>Suspend and sign out</button>
</form>
@endif
@endunless
</section>

@unless ($self)
<section class="bg-surface-container-lowest border border-error/40 p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-error mb-2">Delete account</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-3">Permanently deletes the account, all {{ $user->projects_count }} {{ Str::plural('project', $user->projects_count) }} and every uploaded file. This cannot be undone.</p>
<form action="{{ route('admin.users.destroy', $user) }}" class="space-y-2" method="POST">@csrf @method('DELETE')
<input autocomplete="off" class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-low font-body-sm text-body-sm" name="confirm_email" placeholder="Type {{ $user->email }} to confirm" required type="text"/>
<button class="w-full px-4 py-2.5 bg-error text-on-error rounded-lg font-label-md text-label-md hover:bg-error/90" type="submit">Delete user permanently</button>
</form>
</section>
@endunless

@if ($log->isNotEmpty())
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-3">Admin history</h3>
<ul class="space-y-2">
@foreach ($log as $entry)
<li class="font-body-sm text-body-sm"><span class="text-on-surface">{{ $entry->summary }}</span><span class="block text-[12px] text-on-surface-variant">{{ $entry->admin?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</span></li>
@endforeach
</ul>
</section>
@endif
</aside>
</div>
@endsection
