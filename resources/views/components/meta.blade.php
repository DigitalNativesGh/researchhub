@props(['label'])
<div>
<p class="text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">{{ $label }}</p>
<p class="font-label-md text-label-md text-primary">{{ $slot }}</p>
</div>
