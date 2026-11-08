@extends('layouts.admin')
@section('page_title', 'Payments')
@section('nav', 'payments')
@php $fmt = fn ($v) => \App\Models\Payment::format((float) $v); @endphp
@section('content')
<x-admin.header title="Payments" subtitle="Project payments taken through Paystack (card and Mobile Money).">
<a class="flex items-center gap-1 px-4 py-2 border border-outline-variant rounded-lg text-primary font-label-md text-label-md hover:bg-surface-container-low" href="{{ route('admin.payments.export', request()->only('status', 'q')) }}"><span class="material-symbols-outlined text-[18px]">download</span>Export CSV</a>
<a class="flex items-center gap-1 px-4 py-2 border border-outline-variant rounded-lg text-primary font-label-md text-label-md hover:bg-surface-container-low" href="{{ route('admin.settings') }}"><span class="material-symbols-outlined text-[18px]">sell</span>Edit prices</a>
</x-admin.header>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-gutter">
<x-admin.stat label="Revenue this month" :value="$fmt($totals['month'])"/>
<x-admin.stat label="Revenue all time" :value="$fmt($totals['total'])" :note="$totals['count'].' successful '.Str::plural('payment', $totals['count'])"/>
<x-admin.stat label="Paid, not yet used" :value="$totals['unused']" note="Credits waiting for project details"/>
<div class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-2">Current prices</p>
<ul class="space-y-1 font-body-sm text-body-sm">
@foreach (\App\Enums\DegreeLevel::cases() as $level)
<li class="flex justify-between gap-2"><span class="text-on-surface-variant">{{ $level->shortLabel() }}</span><span class="text-primary font-semibold">{{ $prices[$level->value] > 0 ? $fmt($prices[$level->value]) : 'Free' }}</span><span class="text-[12px] text-on-surface-variant">{{ $byLevel[$level->value]->payments ?? 0 }} paid</span></li>
@endforeach
</ul>
</div>
</div>

<form action="{{ route('admin.payments.index') }}" class="flex flex-col md:flex-row md:items-center justify-between gap-4" method="GET">
<div class="flex flex-wrap gap-2">
@foreach ($statuses as $key => $label)
<a class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors {{ $status === $key ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}" href="{{ route('admin.payments.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $q])) }}">{{ $label }}</a>
@endforeach
</div>
<input name="status" type="hidden" value="{{ $status === 'all' ? '' : $status }}"/>
<div class="relative w-full md:w-80">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
<input class="w-full bg-surface-container-low border border-outline-variant rounded-lg pl-10 pr-4 py-2 font-body-md text-body-md focus:ring-2 focus:ring-primary/10 focus:border-primary" name="q" placeholder="Reference, name or email" type="search" value="{{ $q }}"/>
</div>
</form>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($payments->isEmpty())
<div class="p-stack-md"><x-empty icon="payments" title="No payments found">Payments appear here when students start checkout.</x-empty></div>
@else
<div class="overflow-x-auto custom-scrollbar">
<table class="w-full text-left min-w-[900px]">
<thead><tr class="bg-surface-container-low border-b border-outline-variant">
@foreach (['Date', 'User', 'Research type', 'Amount', 'Method', 'Reference', 'Project', 'Status', ''] as $h)
<th class="py-3 px-4 font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant whitespace-nowrap">{{ $h }}</th>
@endforeach
</tr></thead>
<tbody class="divide-y divide-outline-variant font-body-sm text-body-sm">
@foreach ($payments as $payment)
<tr class="hover:bg-surface-container-low transition-colors">
<td class="py-3 px-4 text-on-surface-variant whitespace-nowrap">{{ $payment->created_at->format('M j, Y H:i') }}</td>
<td class="py-3 px-4">@if ($payment->user)<a class="text-primary hover:underline" href="{{ route('admin.users.show', $payment->user) }}">{{ $payment->user->name }}</a><p class="text-[12px] text-on-surface-variant">{{ $payment->user->email }}</p>@endif</td>
<td class="py-3 px-4 whitespace-nowrap">{{ $payment->degree_level->shortLabel() }}</td>
<td class="py-3 px-4 tabular-nums whitespace-nowrap font-semibold">{{ $payment->displayAmount() }}</td>
<td class="py-3 px-4 whitespace-nowrap">{{ $payment->methodLabel() }}</td>
<td class="py-3 px-4 font-mono text-[12px]">{{ $payment->reference }}</td>
<td class="py-3 px-4">@if ($payment->project)<a class="text-primary hover:underline" href="{{ route('admin.projects.show', $payment->project) }}">{{ Str::limit($payment->project->title, 30) }}</a>@elseif ($payment->consumed_at)<span class="text-on-surface-variant">Project deleted</span>@elseif ($payment->isSuccessful())<span class="text-on-surface-variant">Unused credit</span>@endif</td>
<td class="py-3 px-4" title="{{ $payment->gateway_response }}">@include('admin.payments._status', ['payment' => $payment])</td>
<td class="py-3 px-4">
@unless ($payment->isSuccessful())
<form action="{{ route('admin.payments.verify', $payment) }}" method="POST">@csrf
<button class="flex items-center gap-1 px-3 py-1.5 border border-outline-variant rounded-lg font-label-sm text-label-sm text-primary hover:bg-surface-container-low whitespace-nowrap" title="Ask Paystack for the latest status" type="submit"><span class="material-symbols-outlined text-[16px]">sync</span>Re-check</button>
</form>
@endunless
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<x-admin.pager :items="$payments"/>
@endif
</section>
@endsection
