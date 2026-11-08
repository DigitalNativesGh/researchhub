@props(['user'])
@if ($user?->avatarUrl())
<img alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'rounded-full object-cover flex-shrink-0']) }} src="{{ $user->avatarUrl() }}"/>
@else
<div {{ $attributes->merge(['class' => 'rounded-full bg-secondary-container flex items-center justify-center flex-shrink-0 text-on-secondary-container font-bold']) }}>{{ $user?->initials() }}</div>
@endif
