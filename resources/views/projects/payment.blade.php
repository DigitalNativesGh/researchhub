@extends('layouts.base')
@section('title', 'ResearchHub - Payment')
@push('styles')
<style>
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
    </style>
@endpush
@section('body')
<body class="bg-background text-on-background font-body-md min-h-screen">
<header class="bg-surface border-b border-outline-variant h-16 fixed top-0 w-full z-50 flex items-center px-margin-desktop justify-between">
<div class="flex items-center gap-4">
<a class="hover:bg-surface-container-low p-2 rounded-full transition-colors flex items-center justify-center" href="{{ route('projects.new') }}" title="Back to Research Type">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm font-bold text-primary">Create New Project</h1>
</div>
<div class="flex items-center gap-2">
<span class="font-label-md text-label-md text-on-surface-variant">Step 2 of 5</span>
<div class="w-32 h-2 bg-surface-container-high rounded-full overflow-hidden">
<div class="bg-primary h-full w-2/5 transition-all duration-500"></div>
</div>
</div>
</header>
<main class="pt-24 pb-stack-lg px-margin-desktop max-w-container-max mx-auto">
@include('projects._wizard-steps', ['current' => 2])

<div class="max-w-4xl mx-auto">
<div class="text-center mb-stack-lg">
<h2 class="font-headline-lg text-headline-lg text-primary mb-3">Complete your payment</h2>
<p class="font-body-md text-body-md text-on-surface-variant">Pay once for this project, then add its title and details. Payments are processed securely by Paystack.</p>
</div>

<div class="mb-gutter">@include('partials.flash')</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-start">
<!-- Order summary -->
<section class="lg:col-span-5 bg-white border border-outline-variant rounded-lg p-6">
<h3 class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider text-[11px] font-bold mb-4">Order summary</h3>
<div class="flex items-start gap-4 pb-5 border-b border-outline-variant">
<div class="w-12 h-12 shrink-0 rounded-lg bg-primary/5 flex items-center justify-center"><span class="material-symbols-outlined text-primary text-3xl">{{ $level->icon() }}</span></div>
<div class="flex-1">
<p class="font-headline-sm text-headline-sm text-primary">{{ $level->label() }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $level->description() }}</p>
<a class="inline-flex items-center gap-1 mt-2 text-secondary font-label-sm text-label-sm hover:underline" href="{{ route('projects.new') }}"><span class="material-symbols-outlined text-[16px]">swap_horiz</span>Change research type</a>
</div>
</div>
<ul class="py-5 space-y-2 font-body-sm text-body-sm text-on-surface-variant border-b border-outline-variant">
<li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary">check</span>One research project ({{ $level->typicalRange() }})</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary">check</span>Chapters 1–3 and your data collection instrument</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary">check</span>The complete thesis once you upload your data</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary">check</span>Word or PDF downloads, with progress tracking at every stage</li>
</ul>
<div class="pt-5 flex items-end justify-between">
<span class="font-label-md text-label-md text-on-surface">Total due</span>
<span class="font-headline-lg text-headline-lg text-primary font-bold">{{ \App\Models\Payment::format($price, $currency) }}</span>
</div>
</section>

<!-- Payment -->
<section class="lg:col-span-7 bg-white border border-outline-variant rounded-lg p-6">
<h3 class="font-headline-sm text-headline-sm text-primary mb-1">Payment method</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-5">You choose how to pay on the secure Paystack checkout.</p>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
@if (in_array('mobile_money', $channels, true))
<div class="p-4 border border-outline-variant rounded-lg bg-surface-container-low">
<div class="flex items-center gap-3 mb-2">
<span class="material-symbols-outlined text-primary">smartphone</span>
<span class="font-label-md text-label-md text-primary">Mobile Money</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">MTN MoMo, Telecel Cash, AirtelTigo Money. Approve the prompt on your phone.</p>
</div>
@endif
@if (in_array('card', $channels, true))
<div class="p-4 border border-outline-variant rounded-lg bg-surface-container-low">
<div class="flex items-center gap-3 mb-2">
<span class="material-symbols-outlined text-primary">credit_card</span>
<span class="font-label-md text-label-md text-primary">Card</span>
</div>
<div class="flex items-center gap-2">
<span class="px-2 py-0.5 rounded bg-white border border-outline-variant text-[11px] font-extrabold italic tracking-wide text-[#1a1f71]">VISA</span>
<span class="px-2 py-0.5 rounded bg-white border border-outline-variant text-[11px] font-bold text-on-surface flex items-center gap-1"><span class="inline-flex"><span class="w-3 h-3 rounded-full bg-[#eb001b]"></span><span class="w-3 h-3 rounded-full bg-[#f79e1b] -ml-1.5 opacity-90"></span></span>Mastercard</span>
</div>
</div>
@endif
</div>

@if ($configured && ! \App\Http\Controllers\PaymentController::payableEmail(auth()->user()->email))
<x-alert class="mb-4" icon="mail" kind="error" title="Your email address cannot receive receipts">Paystack sends the receipt to {{ auth()->user()->email }}, which is not a deliverable address. <a class="underline" href="{{ route('profile.edit') }}">Update your email under Profile</a>, then come back to pay.</x-alert>
@endif
@if ($configured)
<form action="{{ route('projects.payment.store') }}" method="POST" onsubmit="this.querySelector('button').disabled = true; this.querySelector('[data-label]').textContent = 'Redirecting to Paystack…';">
@csrf
<button class="w-full bg-primary text-on-primary px-8 py-4 rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary/10 disabled:opacity-60" type="submit">
<span class="material-symbols-outlined">lock</span>
<span data-label>Pay {{ \App\Models\Payment::format($price, $currency) }}</span>
</button>
</form>
@else
<x-alert icon="error" kind="error" title="Online payments are not set up yet">The administrator needs to add the Paystack keys to the server configuration before projects can be paid for.</x-alert>
@endif

<p class="mt-4 flex items-start gap-2 font-body-sm text-body-sm text-on-surface-variant">
<span class="material-symbols-outlined text-[18px]">verified_user</span>
You will be taken to Paystack to pay and brought back here once the payment is confirmed. ResearchHub never sees your card or wallet details.
</p>
</section>
</div>
</div>
</main>
</body>
@endsection
