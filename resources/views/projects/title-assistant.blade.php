@extends('layouts.base')
@section('title', 'ResearchHub - Find a Title')
@php
    $field = 'w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low';
@endphp
@section('body')
<body class="bg-background text-on-background font-body-md min-h-screen">
<header class="bg-surface border-b border-outline-variant h-16 fixed top-0 w-full z-50 flex items-center px-margin-desktop justify-between">
<div class="flex items-center gap-4">
<a class="hover:bg-surface-container-low p-2 rounded-full transition-colors flex items-center justify-center" href="{{ route('projects.create') }}" title="Back to Project Details">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm font-bold text-primary">Create New Project</h1>
</div>
<div class="flex items-center gap-2">
<span class="font-label-md text-label-md text-on-surface-variant">Step 3 of 5</span>
<div class="w-32 h-2 bg-surface-container-high rounded-full overflow-hidden"><div class="bg-primary h-full w-3/5"></div></div>
</div>
</header>
<main class="pt-24 pb-stack-lg px-margin-desktop max-w-container-max mx-auto">
@include('projects._wizard-steps', ['current' => 3])

<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-stack-md">
<div>
<p class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-secondary-container/60 text-primary font-label-sm text-label-sm mb-2"><span class="material-symbols-outlined text-[16px]">lightbulb</span>Title suggestions · AI-assisted</p>
<h2 class="font-headline-lg text-headline-lg text-primary">Help me find a title</h2>
<p class="font-body-md text-body-md text-on-surface-variant max-w-2xl mt-1">Tell us about your area of interest and we will suggest about five possible titles for a {{ $degreeLevel->label() }}. Pick one and edit it, ask for more, or write your own.</p>
</div>
<a class="flex items-center gap-1 px-4 py-2 border border-outline-variant rounded-lg text-primary font-label-md text-label-md hover:bg-surface-container-low whitespace-nowrap" href="{{ route('projects.create') }}"><span class="material-symbols-outlined text-[18px]">edit</span>I'll write my own title</a>
</div>

<div class="mb-gutter">@include('partials.flash')</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-start">
<form action="{{ route('title-assistant.suggest') }}" class="lg:col-span-5 bg-white border border-outline-variant rounded-lg p-6 space-y-4" method="POST">
@csrf
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1">Degree level</label>
<p class="font-body-md text-body-md text-on-surface">{{ $degreeLevel->label() }} <a class="text-secondary font-label-sm text-label-sm hover:underline ml-1" href="{{ route('projects.new') }}">Change</a></p>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="discipline">Field of study</label>
<select class="{{ $field }} appearance-none" id="discipline" name="discipline">
@foreach ($disciplines as $discipline)<option @selected($values['discipline'] === $discipline)>{{ $discipline }}</option>@endforeach
</select>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="topic">Broad topic or area of interest <span class="text-error">*</span></label>
<input class="{{ $field }}" id="topic" maxlength="300" name="topic" placeholder="e.g., mobile money use by small businesses" required type="text" value="{{ $values['topic'] }}"/>
<x-field-error name="topic"/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="keywords">Keywords</label>
<input class="{{ $field }}" id="keywords" maxlength="300" name="keywords" placeholder="e.g., financial inclusion, trust, transaction costs" type="text" value="{{ $values['keywords'] }}"/>
<p class="text-[12px] text-on-surface-variant mt-1">Separate with commas.</p>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="context">Context or setting</label>
<input class="{{ $field }}" id="context" maxlength="300" name="context" placeholder="e.g., market traders in Kumasi, Ghana" type="text" value="{{ $values['context'] }}"/>
<p class="text-[12px] text-on-surface-variant mt-1">Country, sector or population.</p>
</div>
<fieldset>
<legend class="block font-label-md text-label-md text-on-surface mb-2">Preferred research approach</legend>
<div class="grid grid-cols-2 gap-2">
@foreach ($approaches as $value => $label)
<label class="flex items-center gap-2 px-3 py-2 border border-outline-variant rounded-lg cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-surface-container-low">
<input @checked($values['approach'] === $value) class="text-primary" name="approach" type="radio" value="{{ $value }}"/>
<span class="font-body-sm text-body-sm">{{ $label }}</span>
</label>
@endforeach
</div>
</fieldset>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="background">Abstract or background notes <span class="font-normal text-on-surface-variant">(optional)</span></label>
<textarea class="{{ $field }}" id="background" maxlength="3000" name="background" placeholder="Paste an abstract from a key source, or a few lines about what you want to study." rows="4">{{ $values['background'] }}</textarea>
</div>
<button class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary px-6 py-3 rounded-lg font-label-md text-label-md hover:bg-primary/90 disabled:opacity-60" data-busy-text="Finding titles…" type="submit">
<span class="material-symbols-outlined">lightbulb</span>{{ $suggestions ? 'Suggest new titles' : 'Suggest titles' }}
</button>
</form>

<section class="lg:col-span-7 space-y-4" id="suggestions">
<x-alert icon="info" kind="info" title="Suggestions are starting points">Confirm your title with your supervisor. We cannot guarantee that a suggested title has not been used before.</x-alert>

@if ($suggestions === [])
<x-empty icon="lightbulb" title="No suggestions yet">Fill in the form and choose “Suggest titles”. It takes a few seconds.</x-empty>
@else
@foreach ($suggestions as $i => $s)
<div class="bg-white border {{ $i >= $latest ? 'border-primary/40' : 'border-outline-variant' }} rounded-lg p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4" data-suggestion>
<div class="min-w-0">
<p class="font-headline-sm text-headline-sm text-primary">{{ $s['title'] }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">{{ $s['explanation'] }}</p>
</div>
<form action="{{ route('title-assistant.choose') }}" class="shrink-0" method="POST">@csrf
<input name="choice" type="hidden" value="{{ $i }}"/>
<button class="flex items-center gap-1 px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 whitespace-nowrap" type="submit">Use this title<span class="material-symbols-outlined text-[18px]">arrow_forward</span></button>
</form>
</div>
@endforeach
<div class="flex flex-wrap items-center gap-3">
<form action="{{ route('title-assistant.more') }}" method="POST">@csrf
<button class="flex items-center gap-1 px-4 py-2 border border-outline-variant rounded-lg text-primary font-label-md text-label-md hover:bg-surface-container-low" data-busy-text="Finding more…" type="submit"><span class="material-symbols-outlined text-[18px]">refresh</span>Suggest more</button>
</form>
<form action="{{ route('title-assistant.reset') }}" method="POST">@csrf
<button class="px-4 py-2 text-on-surface-variant font-label-md text-label-md hover:text-primary" type="submit">Start over</button>
</form>
</div>
@endif
</section>
</div>
</main>
</body>
@endsection
