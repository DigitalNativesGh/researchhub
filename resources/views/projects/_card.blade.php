@php
    $workflow = $project->workflow_status;
    $group = $workflow->group();
    $percent = $project->progressPercent();
    $badge = match ($group) {
        'review' => 'bg-tertiary-fixed text-on-tertiary-fixed-variant',
        'completed' => 'bg-surface-container-highest text-on-surface-variant',
        'draft' => 'bg-surface-container-high text-on-surface-variant',
        default => 'bg-secondary-container text-on-secondary-container',
    };
@endphp
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 hover:shadow-lg transition-shadow cursor-pointer group {{ $group === 'completed' ? 'relative overflow-hidden' : '' }}" onclick="if (!event.target.closest('a,button')) location.href='{{ route('projects.show', $project) }}'">
@if ($group === 'completed')
<div class="absolute top-0 left-0 w-1 h-full bg-outline"></div>
@endif
<div class="flex justify-between items-start mb-4">
<span class="{{ $badge }} px-2 py-1 rounded font-label-sm text-label-sm uppercase tracking-wider">{{ $workflow->researcherLabel() }}</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-primary group-hover:text-secondary transition-colors mb-2"><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a></h3>
<p class="font-label-sm text-label-sm text-on-surface-variant mb-6 flex items-center gap-2">
<span class="font-bold">ID:</span> {{ $project->reference() }} • <span class="font-bold">Last Update:</span> {{ ($project->workflow_changed_at ?? $project->updated_at)->format('M j, Y') }}
</p>
<div class="space-y-3">
<div class="flex justify-between items-center font-label-sm text-label-sm gap-3">
<span class="text-on-surface-variant truncate">{{ $project->degree_level->shortLabel() }} · {{ $project->research_type->label() }}</span>
<span class="{{ $group === 'review' ? 'text-on-tertiary-fixed-variant' : ($group === 'completed' ? 'text-on-surface-variant' : 'text-primary') }} font-bold">{{ $percent }}%</span>
</div>
<div class="w-full h-2 bg-surface-container-highest rounded-full overflow-hidden">
<div class="h-full {{ $group === 'review' ? 'bg-on-tertiary-fixed-variant' : ($group === 'completed' ? 'bg-outline' : 'bg-primary') }} transition-all duration-1000" style="width: {{ $percent }}%;"></div>
</div>
@if (in_array($workflow, [\App\Enums\WorkflowStatus::Stage1Delivered, \App\Enums\WorkflowStatus::NeedsInfo], true))
<a class="flex items-center gap-2 pt-2 text-on-tertiary-fixed-variant hover:underline" href="{{ $workflow === \App\Enums\WorkflowStatus::NeedsInfo ? route('projects.show', $project) : route('projects.data', $project) }}">
<span class="material-symbols-outlined text-base">{{ $workflow === \App\Enums\WorkflowStatus::NeedsInfo ? 'help' : 'upload' }}</span>
<span class="font-label-sm text-label-sm">{{ $workflow === \App\Enums\WorkflowStatus::NeedsInfo ? 'Our team needs some information from you' : 'Your documents are ready: collect and upload your data' }}</span>
</a>
@elseif ($group === 'completed')
<a class="flex items-center gap-2 pt-2 text-primary hover:underline" href="{{ route('projects.show', $project) }}">
<span class="material-symbols-outlined text-base">download</span>
<span class="font-label-sm text-label-sm">Download your final thesis</span>
</a>
@elseif ($group === 'draft')
<a class="flex items-center gap-2 pt-2 text-primary hover:underline" href="{{ route('projects.setup.sources', $project) }}">
<span class="material-symbols-outlined text-base">upload_file</span>
<span class="font-label-sm text-label-sm">Continue setup: {{ $project->papers_count }} {{ Str::plural('paper', $project->papers_count) }} uploaded</span>
</a>
@else
<div class="flex items-center gap-2 pt-2 text-on-surface-variant">
<span class="material-symbols-outlined text-base">hourglass_top</span>
<span class="font-label-sm text-label-sm">{{ $workflow->researcherMessage() }}</span>
</div>
@endif
</div>
</div>
