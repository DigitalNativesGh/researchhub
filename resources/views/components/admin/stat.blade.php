@props(['label', 'value', 'note' => null, 'icon' => null, 'href' => null, 'tone' => null])
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl'.($href ? ' hover:border-primary transition-colors' : '')]) }}>
<div class="flex items-center justify-between">
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">{{ $label }}</p>
@if ($icon)<span class="material-symbols-outlined text-[20px] {{ $tone === 'error' ? 'text-error' : 'text-on-surface-variant' }}">{{ $icon }}</span>@endif
</div>
<p class="font-headline-lg text-headline-lg mt-2 {{ $tone === 'error' ? 'text-error' : 'text-primary' }}">{{ $value }}</p>
@if ($note)<p class="text-[12px] text-on-surface-variant mt-1">{{ $note }}</p>@endif
</{{ $href ? 'a' : 'div' }}>
