@props(['kind' => 'info', 'icon' => 'info', 'title'])
@php
    $classes = [
        'info' => 'bg-secondary-container/40 border-secondary-container text-on-secondary-fixed-variant',
        'warn' => 'bg-surface-container-low border-outline-variant text-on-surface',
        'error' => 'bg-error-container/50 border-error-container text-on-error-container',
        'success' => 'bg-tertiary-fixed/40 border-tertiary-fixed text-on-tertiary-fixed-variant',
    ][$kind];
@endphp
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 p-4 border rounded-lg '.$classes]) }}>
<span class="material-symbols-outlined text-[20px] mt-0.5">{{ $icon }}</span>
<div>
<p class="font-label-md text-label-md">{{ $title }}</p>
@if (trim($slot) !== '')<div class="font-body-sm text-body-sm mt-1 opacity-90">{{ $slot }}</div>@endif
</div>
</div>
