@props(['kind' => 'pending'])
@php
    $classes = [
        'progress' => 'bg-secondary-container text-on-secondary-container',
        'done' => 'bg-tertiary-fixed text-on-tertiary-fixed',
        'pending' => 'bg-surface-container-high text-on-surface-variant',
        'failed' => 'bg-error-container text-on-error-container',
        'approval' => 'bg-primary-fixed text-on-primary-fixed-variant',
    ][$kind] ?? 'bg-surface-container-high text-on-surface-variant';
@endphp
<span {{ $attributes->merge(['class' => $classes.' px-3 py-1 rounded-full text-[12px] font-bold uppercase tracking-wider whitespace-nowrap inline-flex items-center gap-1']) }}>@if ($kind === 'progress')<span class="w-1.5 h-1.5 rounded-full bg-on-secondary-container animate-pulse"></span>@endif{{ $slot }}</span>
