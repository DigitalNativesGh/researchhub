@extends('layouts.base')
@section('title', isset($project) ? 'ResearchHub - Edit Project' : 'ResearchHub - Create New Project')
@push('styles')
<style>
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
      .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
      }
      .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
      }
      .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #D1D5DB;
        border-radius: 10px;
      }
      .peer:checked ~ .peer-checked\:type-card-selected,
      .type-card-selected {
        border-color: #041632 !important;
        background-color: #f3f4f5 !important;
        box-shadow: 0 4px 12px rgba(4, 22, 50, 0.08) !important;
      }
    </style>
@endpush
@section('body')
<body class="bg-background text-on-background font-body-md min-h-screen">
<!-- Navigation Suppression: Wizard is a Task-Focused Sub-page -->
<header class="bg-surface border-b border-outline-variant h-16 fixed top-0 w-full z-50 flex items-center px-margin-desktop justify-between">
<div class="flex items-center gap-4">
<a class="hover:bg-surface-container-low p-2 rounded-full transition-colors flex items-center justify-center" href="{{ isset($project) ? route('projects.show', $project) : route('projects.index') }}" title="Back">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm font-bold text-primary">{{ isset($project) ? 'Edit Project' : 'Create New Project' }}</h1>
</div>
<div class="flex items-center gap-2">
@unless (isset($project))
<span class="font-label-md text-label-md text-on-surface-variant">Step 3 of 5</span>
<div class="w-32 h-2 bg-surface-container-high rounded-full overflow-hidden">
<div class="bg-primary h-full w-3/5 transition-all duration-500"></div>
</div>
@endunless
</div>
</header>
<main class="pt-24 pb-stack-lg px-margin-desktop max-w-container-max mx-auto">
@unless (isset($project))
@include('projects._wizard-steps', ['current' => 3])
<div class="mb-gutter">@include('partials.flash', ['hideErrorSummary' => true])</div>
@endunless
<form action="{{ isset($project) ? route('projects.update', $project) : route('projects.store') }}" class="grid grid-cols-1 lg:grid-cols-12 gap-gutter" method="POST">
@csrf
@isset($project) @method('PUT') @endisset
@if ($errors->any())
<div class="lg:col-span-12">
<x-alert icon="error" kind="error" title="Please fix the highlighted fields">{{ $errors->first() }}</x-alert>
</div>
@endif
@php $chosen = \App\Enums\DegreeLevel::tryFrom((string) $values['degree_level']) ?? \App\Enums\DegreeLevel::Undergraduate; @endphp
<!-- Research type chosen (and paid for) in steps 1-2 -->
<div class="lg:col-span-12">
<input name="degree_level" type="hidden" value="{{ $chosen->value }}"/>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 bg-white border border-outline-variant rounded-lg">
<div class="flex items-center gap-4">
<div class="w-12 h-12 rounded-lg bg-primary/5 flex items-center justify-center"><span class="material-symbols-outlined text-primary text-3xl">{{ $chosen->icon() }}</span></div>
<div>
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Research Type</p>
<h2 class="font-headline-sm text-headline-sm text-primary">{{ $chosen->label() }}</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $chosen->description() }}</p>
</div>
</div>
@isset($project)
<span class="flex items-center gap-1 px-3 py-1.5 bg-surface-container-high rounded-lg text-on-surface-variant font-label-sm text-label-sm whitespace-nowrap"><span class="material-symbols-outlined text-[16px]">lock</span>Fixed for this project</span>
@else
<span class="flex items-center gap-1 px-3 py-1.5 bg-tertiary-fixed text-on-tertiary-fixed rounded-lg font-label-sm text-label-sm whitespace-nowrap"><span class="material-symbols-outlined text-[16px]">verified</span>{{ ($payment = auth()->user()->paymentCreditFor($chosen)) ? 'Paid · '.$payment->displayAmount() : 'Included' }}</span>
@endisset
</div>
<x-field-error name="degree_level"/>
</div>
<!-- Project Details Form -->
<div class="lg:col-span-8">
<div class="bg-white border border-outline-variant p-8 rounded-lg">
<h2 class="font-headline-md text-headline-md text-primary mb-stack-md">Project Details</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-stack-md">
@unless (isset($project))
<div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
<div class="flex items-start gap-3 p-4 rounded-lg border-2 border-primary bg-surface-container-low">
<span class="material-symbols-outlined text-primary">edit</span>
<div><p class="font-label-md text-label-md text-primary">I have a title</p><p class="font-body-sm text-body-sm text-on-surface-variant">Type it below. You can refine it with your supervisor later.</p></div>
</div>
<a class="flex items-start gap-3 p-4 rounded-lg border border-outline-variant hover:border-primary transition-colors" href="{{ route('title-assistant') }}">
<span class="material-symbols-outlined text-secondary">lightbulb</span>
<div><p class="font-label-md text-label-md text-primary">Help me find a title</p><p class="font-body-sm text-body-sm text-on-surface-variant">Describe your area of interest and get about five suggested titles.</p></div>
</a>
</div>
@endunless
<div class="md:col-span-2">
<label class="block font-label-md text-label-md text-on-surface mb-2" for="title">Project Title</label>
<input class="w-full px-4 py-3 border {{ $errors->has('title') ? 'border-error' : 'border-outline-variant' }} rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="title" maxlength="500" name="title" placeholder="e.g., Mobile Money Adoption among Small Traders in Kumasi, Ghana" required="" type="text" value="{{ $values['title'] }}"/>
@if ($suggestedTitle)
<p class="mt-2 flex items-center gap-1 text-[12px] text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">lightbulb</span>From the title suggestions. Edit it freely; suggestions are starting points to confirm with your supervisor.</p>
@endif
<x-field-error name="title"/>
</div>
<div class="md:col-span-2">
<label class="block font-label-md text-label-md text-on-surface mb-2" for="institution">Institution</label>
<input class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="institution" maxlength="255" name="institution" placeholder="e.g., University of Ghana" type="text" value="{{ $values['institution'] }}"/>
<x-field-error name="institution"/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-2" for="discipline">Field of Study</label>
<select class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low appearance-none" id="discipline" name="discipline">
@foreach ($disciplines as $discipline)
<option @selected($values['discipline'] === $discipline)>{{ $discipline }}</option>
@endforeach
</select>
<x-field-error name="discipline"/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-2" for="word_count_target">Required Length (words)</label>
<input class="w-full px-4 py-3 border {{ $errors->has('word_count_target') ? 'border-error' : 'border-outline-variant' }} rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="word_count_target" max="150000" min="1000" name="word_count_target" placeholder="e.g., 5000" type="number" value="{{ $values['word_count_target'] }}"/>
<x-field-error name="word_count_target"/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-2" for="research_type">Research Approach</label>
<select class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low appearance-none" id="research_type" name="research_type">
@foreach (\App\Enums\ResearchType::cases() as $type)
<option @selected($values['research_type'] === $type->value) value="{{ $type->value }}">{{ $type->label() }}</option>
@endforeach
</select>
<x-field-error name="research_type"/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-2" for="citation_style">Citation Style</label>
<select class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low appearance-none" id="citation_style" name="citation_style">
@foreach (\App\Enums\CitationStyle::cases() as $style)
<option @selected($values['citation_style'] === $style->value) value="{{ $style->value }}">{{ $style->label() }}</option>
@endforeach
</select>
<x-field-error name="citation_style"/>
</div>
<div class="md:col-span-2">
<label class="block font-label-md text-label-md text-on-surface mb-2" for="description">Description/Objectives</label>
<textarea class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="description" maxlength="3000" name="description" placeholder="Briefly describe the core research question and key objectives..." rows="4">{{ $values['description'] }}</textarea>
<x-field-error name="description"/>
</div>
<div class="md:col-span-2">
<label class="block font-label-md text-label-md text-on-surface mb-2" for="guidelines">Institutional Guidelines</label>
<textarea class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="guidelines" maxlength="5000" name="guidelines" placeholder="Formatting, structure or length rules from your institution. You can also upload guideline documents or templates with your sources." rows="3">{{ $values['guidelines'] }}</textarea>
<x-field-error name="guidelines"/>
</div>
<div class="md:col-span-2">
<label class="block font-label-md text-label-md text-on-surface mb-2" for="writer_notes">Notes for Our Team</label>
<textarea class="w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="writer_notes" maxlength="5000" name="writer_notes" placeholder="Anything else we should know: your supervisor's expectations, chapters already drafted, preferred methods..." rows="3">{{ $values['writer_notes'] }}</textarea>
<x-field-error name="writer_notes"/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-2" for="deadline">Deadline</label>
<div class="relative">
<input class="w-full px-4 py-3 border {{ $errors->has('deadline') ? 'border-error' : 'border-outline-variant' }} rounded-lg focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all bg-surface-container-low" id="deadline" min="{{ now()->addDay()->toDateString() }}" name="deadline" type="date" value="{{ $values['deadline'] }}"/>
</div>
<x-field-error name="deadline"/>
</div>
</div>
</div>
</div>
<!-- Side Summary (Bento-style Info) -->
<div class="lg:col-span-4">
<div class="sticky top-24 flex flex-col gap-6">
<div class="bg-primary-container p-6 rounded-lg text-on-primary-container">
<div class="flex items-center gap-2 mb-4">
<span class="material-symbols-outlined text-on-tertiary-container">info</span>
<h3 class="font-label-md text-label-md font-bold uppercase tracking-wider">How It Works</h3>
</div>
<p class="font-body-sm text-body-sm leading-relaxed mb-4">Next you upload the sources your thesis will build on and submit your project. Our academic team prepares your Chapters 1–3 and data collection instrument; once you have collected your data, we prepare your complete thesis.</p>
<div class="flex flex-wrap gap-2">
<span class="px-2 py-1 bg-white/10 rounded text-[10px] font-bold">YOUR SOURCES ONLY</span>
<span class="px-2 py-1 bg-white/10 rounded text-[10px] font-bold">PREPARED BY OUR TEAM</span>
<span class="px-2 py-1 bg-white/10 rounded text-[10px] font-bold">THREE CLEAR STAGES</span>
</div>
</div>
<div class="bg-surface-container border border-outline-variant p-6 rounded-lg overflow-hidden relative group">
<div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors"></div>
<h3 class="font-label-md text-label-md text-primary mb-4">Visual Reference</h3>
<div class="aspect-video w-full rounded border border-outline-variant overflow-hidden mb-4 bg-white">
<img alt="A researcher analysing data in a university library" class="w-full h-full object-cover" src="{{ \App\Support\Assets::image('wizard_reference') }}"/>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Our team works only from the sources, data and guidelines you provide.</p>
</div>
</div>
</div>
<!-- Sticky Footer Action -->
<div class="lg:col-span-12 mt-stack-lg border-t border-outline-variant pt-gutter flex items-center justify-between">
<div>
@unless (isset($project))
<button class="px-6 py-3 font-label-md text-label-md text-primary hover:bg-surface-container-low transition-colors rounded-lg" name="intent" type="submit" value="draft">
                        Save as Draft
                    </button>
@endunless
</div>
<div class="flex items-center gap-gutter">
<div class="text-right hidden sm:block">
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Typical Length</p>
<p class="font-headline-sm text-headline-sm text-primary font-bold" id="total-price">{{ (\App\Enums\DegreeLevel::tryFrom((string) $values['degree_level']) ?? \App\Enums\DegreeLevel::Undergraduate)->typicalRange() }}</p>
</div>
<button class="bg-primary text-on-primary px-8 py-4 rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all flex items-center gap-2 shadow-lg shadow-primary/10" name="intent" type="submit" value="next">
                        {{ isset($project) ? 'Save Changes' : 'Next: Sources' }}
                        <span class="material-symbols-outlined">{{ isset($project) ? 'check' : 'arrow_forward' }}</span>
</button>
</div>
</div>
</form>
</main>
</body>
@endsection
