@props(['icon', 'title', 'action' => null, 'href' => null, 'actionIcon' => 'add'])
<div class="flex flex-col items-center justify-center text-center py-stack-lg px-stack-md border-2 border-dashed border-outline-variant rounded-xl bg-surface-container-low/30">
<div class="w-14 h-14 rounded-xl bg-secondary-container/40 text-secondary flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-[28px]">{{ $icon }}</span>
</div>
<h4 class="font-headline-sm text-headline-sm text-primary mb-1">{{ $title }}</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant max-w-md {{ $action ? 'mb-6' : '' }}">{{ $slot }}</p>
@if ($action)
<a class="bg-primary text-on-primary px-6 py-2.5 rounded-lg font-label-md text-label-md flex items-center gap-2 hover:bg-primary-container transition-all shadow-sm" href="{{ $href }}">
<span class="material-symbols-outlined text-base">{{ $actionIcon }}</span>
{{ $action }}
</a>
@endif
</div>
