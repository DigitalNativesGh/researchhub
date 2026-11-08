@props(['kind' => 'pdf'])
@php
    [$classes, $icon] = match ($kind) {
        'pdf' => ['bg-error-container/20 text-error', 'picture_as_pdf'],
        'csv' => ['bg-on-primary-container/20 text-on-primary-container', 'csv'],
        'xlsx', 'xls' => ['bg-on-primary-container/20 text-on-primary-container', 'table_chart'],
        default => ['bg-secondary-container/30 text-secondary', 'description'],
    };
@endphp
<div class="w-10 h-10 {{ $classes }} rounded flex items-center justify-center flex-shrink-0">
<span class="material-symbols-outlined">{{ $icon }}</span>
</div>
