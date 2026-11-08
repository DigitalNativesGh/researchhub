@props(['title', 'subtitle' => null])
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="font-headline-lg text-headline-lg text-primary">{{ $title }}</h2>
@if ($subtitle)<p class="font-body-md text-body-md text-on-surface-variant mt-1">{{ $subtitle }}</p>@endif
</div>
@if (trim($slot) !== '')<div class="flex items-center gap-3 flex-wrap">{{ $slot }}</div>@endif
</div>
