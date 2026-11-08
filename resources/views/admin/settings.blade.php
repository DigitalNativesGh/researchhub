@extends('layouts.admin')
@section('page_title', 'Platform Settings')
@section('nav', 'settings')
@section('content')
<x-admin.header title="Platform Settings" subtitle="Changes apply immediately to every user. Limits override the values in config/researchhub.php."/>

<form action="{{ route('admin.settings.update') }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl divide-y divide-outline-variant" method="POST">
@csrf @method('PUT')
@foreach ($definitions as $key => [$label, $type, $help])
@php $value = old($key, $values[$key]); @endphp
<div class="p-stack-md grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
<div class="md:col-span-5">
<label class="block font-label-md text-label-md text-on-surface" for="{{ $key }}">{{ $label }}</label>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">{{ $help }}</p>
</div>
<div class="md:col-span-7">
@if ($type === 'bool')
<label class="inline-flex items-center gap-3 cursor-pointer">
<input @checked($value) class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary/20" id="{{ $key }}" name="{{ $key }}" type="checkbox" value="1"/>
<span class="font-body-md text-body-md">{{ $value ? 'On' : 'Off' }}</span>
</label>
@elseif ($type === 'text')
<textarea class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-low focus:ring-2 focus:ring-primary/10 focus:border-primary" id="{{ $key }}" maxlength="500" name="{{ $key }}" rows="2">{{ $value }}</textarea>
@elseif (str_starts_with($type, 'select:'))
<select class="w-full md:w-64 px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-low" id="{{ $key }}" name="{{ $key }}">
@foreach (explode(',', Str::after($type, 'select:')) as $option)
<option @selected($value === $option) value="{{ $option }}">{{ ucfirst($option) }}</option>
@endforeach
</select>
@elseif ($type === 'decimal')
<div class="flex items-center gap-2">
<span class="font-label-md text-label-md text-on-surface-variant">{{ \App\Models\Payment::currency() }}</span>
<input class="w-full md:w-40 px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-low focus:ring-2 focus:ring-primary/10 focus:border-primary" id="{{ $key }}" min="0" name="{{ $key }}" step="0.01" type="number" value="{{ $value }}"/>
</div>
@else
<input class="w-full md:w-40 px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-low focus:ring-2 focus:ring-primary/10 focus:border-primary" id="{{ $key }}" min="1" name="{{ $key }}" type="number" value="{{ $value }}"/>
@endif
<x-field-error :name="$key"/>
</div>
</div>
@endforeach
<div class="p-stack-md flex justify-end">
<button class="bg-primary text-on-primary px-8 py-3 rounded-lg font-label-md text-label-md hover:bg-primary/90 flex items-center gap-2" type="submit"><span class="material-symbols-outlined">save</span>Save settings</button>
</div>
</form>

<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-2">Granting administrator access</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">Promote an existing account from its page under <a class="text-secondary hover:underline" href="{{ route('admin.users.index') }}">Users</a>, or from the server:</p>
<pre class="mt-3 p-3 bg-surface-container-low rounded font-mono text-[12px]">php artisan researchhub:admin someone@example.com</pre>
</section>
@endsection
