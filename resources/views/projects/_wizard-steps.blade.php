{{-- Wizard stepper for the research type, payment and details steps. $current: 1-5. --}}
@php $steps = ['Research Type', 'Payment', 'Title & Details', 'Sources', 'Review']; @endphp
<div class="flex items-center justify-center mb-stack-lg">
<div class="flex items-center w-full max-w-3xl">
@foreach ($steps as $i => $label)
@php $n = $i + 1; @endphp
@if ($i > 0)
<div class="h-px bg-outline-variant flex-1 mb-6"></div>
@endif
@php $tag = ($n < $current && $n === 1) ? 'a' : 'div'; @endphp
<{{ $tag }} class="flex flex-col items-center flex-1" @if ($tag === 'a') href="{{ route('projects.new') }}" @endif>
@if ($n < $current)
<div class="w-10 h-10 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold mb-2"><span class="material-symbols-outlined text-[20px]">check</span></div>
<span class="font-label-md text-label-md text-on-surface">{{ $label }}</span>
@elseif ($n === $current)
<div class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold mb-2 shadow-sm">{{ $n }}</div>
<span class="font-label-md text-label-md text-primary">{{ $label }}</span>
@else
<div class="w-10 h-10 rounded-full bg-surface-container-highest text-on-surface-variant flex items-center justify-center font-bold mb-2">{{ $n }}</div>
<span class="font-label-md text-label-md text-on-surface-variant">{{ $label }}</span>
@endif
</{{ $tag }}>
@endforeach
</div>
</div>
