{{-- One uploaded data file in the researcher's data step (the page script re-renders these while files are checked). --}}
@php $report = $file->validation_report ?? []; @endphp
<li class="px-4 py-3" data-dataset="{{ $file->id }}">
<div class="flex items-center justify-between gap-4">
<div class="min-w-0">
<p class="font-label-md text-label-md text-on-surface truncate">{{ $file->original_name }}</p>
<p class="text-[12px] text-on-surface-variant">@if ($file->kind !== 'transcript' && isset($report['rows']) && $file->status->value !== 'invalid'){{ $report['rows'] }} rows · {{ count(array_filter($report['header'] ?? [])) }} columns · @endif{{ $file->sizeForHumans() }}</p>
</div>
<div class="flex items-center gap-2 shrink-0">
<x-badge :kind="$file->status->badge()">{{ $file->status->label() }}</x-badge>
<form action="{{ route('datasets.destroy', $file) }}" method="POST">@csrf @method('DELETE')<button class="p-2 text-on-surface-variant hover:text-error" title="Remove" type="submit"><span class="material-symbols-outlined text-[20px]">delete</span></button></form>
</div>
</div>
@if (! empty($report['errors']) || ! empty($report['warnings']))
<ul class="mt-2 text-[12px] text-on-surface-variant list-disc pl-5 space-y-1">
@foreach ($report['errors'] ?? [] as $error)<li class="text-error">{{ $error }}</li>@endforeach
@foreach ($report['warnings'] ?? [] as $warning)<li>{{ $warning }}</li>@endforeach
</ul>
@endif
</li>
