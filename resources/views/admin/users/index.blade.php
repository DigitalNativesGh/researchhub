@extends('layouts.admin')
@section('page_title', 'Users')
@section('nav', 'users')
@section('content')
<x-admin.header title="Users" subtitle="Every account on the platform. Open one to manage access, verification and data."/>

<form action="{{ route('admin.users.index') }}" class="flex flex-col md:flex-row md:items-center justify-between gap-4" method="GET">
<div class="flex flex-wrap gap-2">
@foreach ($filters as $key => $label)
<a class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors {{ $filter === $key ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}" href="{{ route('admin.users.index', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $q])) }}">{{ $label }}</a>
@endforeach
</div>
<input name="filter" type="hidden" value="{{ $filter === 'all' ? '' : $filter }}"/>
<div class="relative w-full md:w-80">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
<input class="w-full bg-surface-container-low border border-outline-variant rounded-lg pl-10 pr-4 py-2 font-body-md text-body-md focus:ring-2 focus:ring-primary/10 focus:border-primary" name="q" placeholder="Name, email or institution" type="search" value="{{ $q }}"/>
</div>
</form>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($users->isEmpty())
<div class="p-stack-md"><x-empty icon="person_search" title="No users found">Try a different search or filter.</x-empty></div>
@else
<div class="overflow-x-auto custom-scrollbar">
<table class="w-full text-left min-w-[760px]">
<thead><tr class="bg-surface-container-low border-b border-outline-variant">
@foreach (['User', 'Role', 'Projects', 'AI cost', 'Last sign-in', 'Joined', 'Status'] as $h)
<th class="py-3 px-4 font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant whitespace-nowrap">{{ $h }}</th>
@endforeach
</tr></thead>
<tbody class="divide-y divide-outline-variant font-body-sm text-body-sm">
@foreach ($users as $u)
<tr class="hover:bg-surface-container-low transition-colors">
<td class="py-3 px-4">
<a class="flex items-center gap-3" href="{{ route('admin.users.show', $u) }}">
<x-avatar :user="$u" class="w-8 h-8 text-[10px]"/>
<div class="min-w-0">
<p class="font-label-md text-label-md text-primary hover:underline truncate">{{ $u->name }}</p>
<p class="text-[12px] text-on-surface-variant truncate">{{ $u->email }}</p>
</div>
</a>
</td>
<td class="py-3 px-4 text-on-surface-variant whitespace-nowrap">{{ $u->roleLabel() }}</td>
<td class="py-3 px-4 tabular-nums">{{ $u->projects_count }}</td>
<td class="py-3 px-4 tabular-nums">${{ number_format((float) $u->ai_cost, 2) }}</td>
<td class="py-3 px-4 text-on-surface-variant whitespace-nowrap">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
<td class="py-3 px-4 text-on-surface-variant whitespace-nowrap">{{ $u->created_at->format('M j, Y') }}</td>
<td class="py-3 px-4"><div class="flex flex-wrap gap-1">
@if ($u->is_admin)<x-badge kind="approval">Admin</x-badge>@endif
@if ($u->isSuspended())<x-badge kind="failed">Suspended</x-badge>
@elseif (! $u->hasVerifiedEmail())<x-badge kind="pending">Unverified</x-badge>
@else<x-badge kind="done">Active</x-badge>@endif
</div></td>
</tr>
@endforeach
</tbody>
</table>
</div>
<x-admin.pager :items="$users"/>
@endif
</section>
@endsection
