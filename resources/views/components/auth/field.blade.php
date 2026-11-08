@props(['name', 'label', 'icon', 'type' => 'text', 'placeholder' => '', 'value' => null, 'autocomplete' => null])
<div class="space-y-2">
<div class="flex justify-between items-center">
<label class="font-label-md text-label-md text-on-surface-variant" for="{{ $name }}">{{ $label }}</label>
{{ $right ?? '' }}
</div>
<div class="relative group">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px] transition-colors group-focus-within:text-primary">{{ $icon }}</span>
<input @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif class="w-full pl-10 pr-4 py-3 bg-white border {{ $errors->has($name) ? 'border-error' : 'border-outline-variant' }} rounded-lg font-body-md text-body-md text-on-surface input-ring transition-all" id="{{ $name }}" name="{{ $name }}" placeholder="{{ $placeholder }}" required="" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes }}/>
</div>
<x-field-error :name="$name"/>
</div>
