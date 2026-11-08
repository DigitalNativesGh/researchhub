@props(['items'])
@if ($items->hasPages())
<div class="px-stack-md py-4 border-t border-outline-variant flex items-center justify-between font-label-sm text-label-sm text-on-surface-variant">
<span>Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }}</span>
<div class="flex items-center gap-2">
@if ($items->previousPageUrl())<a class="px-3 py-1.5 border border-outline-variant rounded hover:bg-surface-container-low" href="{{ $items->previousPageUrl() }}">Previous</a>@endif
@if ($items->nextPageUrl())<a class="px-3 py-1.5 border border-outline-variant rounded hover:bg-surface-container-low" href="{{ $items->nextPageUrl() }}">Next</a>@endif
</div>
</div>
@endif
