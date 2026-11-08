@extends('layouts.app')
@section('title', 'Profile Settings | ResearchHub')
@section('body_class', 'bg-surface')
@section('main_class', 'ml-64 mt-16 p-stack-lg min-h-screen')
@section('nav', 'profile')
@push('styles')
<style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
            color: #191c1d;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .sidebar-active {
            font-variation-settings: 'FILL' 1;
        }
        /* Custom subtle focus rings for accessibility */
        .focus-academic:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(4, 22, 50, 0.1);
            border-color: #041632;
        }
    </style>
@endpush
@php
    $toggle = "w-10 h-5 rounded-full bg-outline-variant text-primary focus:ring-primary/20 appearance-none relative transition-all checked:bg-primary after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all checked:after:translate-x-5 cursor-pointer";
    $input = 'w-full px-4 py-2.5 rounded-lg border border-outline-variant bg-surface-bright focus-academic text-body-md';
    $passwordErrors = $errors->getBag('password');
    $deletionErrors = $errors->getBag('deletion');
@endphp
@section('main')
<div class="max-w-[1000px] mx-auto">
<!-- Page Header -->
<div class="mb-stack-md">
<h2 class="font-headline-lg text-headline-lg text-primary">Profile Settings</h2>
<p class="font-body-md text-body-md text-on-surface-variant">Manage your details, notifications and account security.</p>
</div>
<div class="mb-stack-md space-y-3">@include('partials.flash')</div>
<!-- Separate forms referenced by the inputs below via the form="" attribute -->
<form action="{{ route('profile.photo.store') }}" enctype="multipart/form-data" id="photo-form" method="POST">@csrf</form>
<form action="{{ route('profile.photo.destroy') }}" id="photo-remove-form" method="POST">@csrf @method('DELETE')</form>
<form action="{{ route('profile.password') }}" id="password-form" method="POST">@csrf @method('PUT')</form>
<form action="{{ route('profile.update') }}" id="profile-form" method="POST">
@csrf
@method('PUT')
<!-- Settings Content: Bento/Modular Grid -->
<div class="grid grid-cols-12 gap-gutter">
<!-- Section 1: Personal Information -->
<section class="col-span-12 lg:col-span-8 bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md shadow-sm">
<div class="flex items-center gap-3 mb-6">
<span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">badge</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Personal Information</h3>
</div>
<div class="flex flex-col md:flex-row gap-stack-md mb-8">
<div class="flex-shrink-0">
<div class="relative group">
<label class="block w-32 h-32 rounded-xl bg-surface-container overflow-hidden border border-outline-variant relative cursor-pointer" title="Change photo">
@if ($user->avatarUrl())
<img alt="{{ $user->name }}" class="w-full h-full object-cover" src="{{ $user->avatarUrl() }}"/>
@else
<span class="w-full h-full flex items-center justify-center bg-secondary-container text-on-secondary-container font-headline-md text-headline-md">{{ $user->initials() }}</span>
@endif
<span class="absolute inset-0 bg-primary/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
<span class="material-symbols-outlined text-white text-3xl">photo_camera</span>
</span>
<input accept="image/jpeg,image/png,image/webp" class="sr-only" data-auto-submit form="photo-form" id="photo-input" name="photo" type="file"/>
</label>
<label for="photo-input" class="mt-3 text-label-md font-label-md text-primary hover:underline flex items-center gap-1 cursor-pointer">
                                    Change photo
                                </label>
@if ($user->avatar_path)
<button class="mt-1 text-label-sm font-label-sm text-on-surface-variant hover:text-error" form="photo-remove-form" type="submit">Remove</button>
@endif
<x-field-error name="photo"/>
<p class="mt-1 text-[11px] text-on-surface-variant max-w-[8rem]">JPG, PNG or WebP, up to 2 MB.</p>
</div>
</div>
<div class="grow grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="name">Full Name</label>
<input autocomplete="name" class="{{ $input }}" id="name" name="name" required type="text" value="{{ old('name', $user->name) }}"/>
<x-field-error name="name"/>
</div>
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="email">Email Address</label>
<input autocomplete="email" class="{{ $input }}" id="email" name="email" required type="email" value="{{ old('email', $user->email) }}"/>
<x-field-error name="email"/>
<p class="text-[11px] text-on-surface-variant">Changing it asks you to verify the new address.</p>
</div>
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="phone">Phone Number</label>
<input autocomplete="tel" class="{{ $input }}" id="phone" name="phone" placeholder="Optional" type="tel" value="{{ old('phone', $user->phone) }}"/>
<x-field-error name="phone"/>
</div>
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="timezone">Timezone</label>
@php $tz = old('timezone', $user->timezone ?? config('app.timezone')); @endphp
<select class="{{ $input }} appearance-none" id="timezone" name="timezone">
@foreach ($timezones as $zone)
<option @selected($tz === $zone) value="{{ $zone }}">{{ str_replace('_', ' ', $zone) }} (UTC{{ now($zone)->format('P') }})</option>
@endforeach
</select>
<x-field-error name="timezone"/>
</div>
</div>
</div>
</section>
<!-- Section 2: Account Settings (Quick Actions) -->
<section class="col-span-12 lg:col-span-4 bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md shadow-sm">
<div class="flex items-center gap-3 mb-6">
<span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">security</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Account</h3>
</div>
<div class="flex flex-col gap-4">
<div class="p-4 rounded-lg bg-surface-container-low border border-outline-variant/50">
<p class="font-label-md text-label-md text-on-surface mb-1">Password</p>
<p class="text-body-sm font-body-sm text-on-surface-variant mb-3">{{ $user->password_changed_at ? 'Last changed '.$user->password_changed_at->diffForHumans() : 'Set when you created your account' }}</p>
<button class="w-full py-2 bg-surface-container-lowest border border-primary text-primary font-label-md text-label-md rounded hover:bg-primary/5 transition-colors {{ $passwordErrors->any() ? 'hidden' : '' }}" data-hide-self data-toggle="#password-panel" type="button">
                                Change Password
                            </button>
<div class="{{ $passwordErrors->any() ? '' : 'hidden' }} space-y-3" id="password-panel">
@foreach (['current_password' => ['Current password', 'current-password'], 'password' => ['New password', 'new-password'], 'password_confirmation' => ['Confirm new password', 'new-password']] as $field => [$label, $autocomplete])
<div class="flex flex-col gap-1">
<label class="font-label-sm text-label-sm text-on-surface" for="{{ $field }}">{{ $label }}</label>
<input autocomplete="{{ $autocomplete }}" class="{{ $input }}" form="password-form" id="{{ $field }}" name="{{ $field }}" required type="password"/>
@if ($passwordErrors->has($field))
<p class="flex items-center gap-1 text-[12px] font-medium text-error"><span class="material-symbols-outlined text-[16px]">error</span>{{ $passwordErrors->first($field) }}</p>
@endif
</div>
@endforeach
<button class="w-full py-2 bg-primary text-on-primary font-label-md text-label-md rounded hover:opacity-90 transition-colors" form="password-form" type="submit">Update Password</button>
</div>
</div>
<div class="flex flex-col gap-3">
<p class="font-label-md text-label-md text-on-surface">Notifications</p>
<label class="flex items-center justify-between cursor-pointer group">
<span class="text-body-md font-body-md text-on-surface-variant group-hover:text-primary transition-colors" title="Emails when a project needs your review, finishes or fails">Email alerts</span>
<input name="email_alerts" type="hidden" value="0"/>
<input @checked(old('email_alerts', $user->email_alerts)) class="{{ $toggle }}" name="email_alerts" type="checkbox" value="1"/>
</label>
<label class="flex items-center justify-between cursor-pointer group">
<span class="text-body-md font-body-md text-on-surface-variant group-hover:text-primary transition-colors" title="Browser notifications while ResearchHub is open in a tab">Push notifications</span>
<input name="push_notifications" type="hidden" value="0"/>
<input @checked(old('push_notifications', $user->push_notifications)) class="{{ $toggle }}" data-push-toggle name="push_notifications" type="checkbox" value="1"/>
</label>
<p class="text-[11px] text-on-surface-variant">Push notifications appear in this browser while ResearchHub is open.</p>
</div>
</div>
</section>
<!-- Section 3: Academic Credentials -->
<section class="col-span-12 bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md shadow-sm">
<div class="flex items-center gap-3 mb-6">
<span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">school</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Academic Credentials</h3>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-stack-md">
<div class="flex flex-col gap-4">
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="institution">Institution / University</label>
<div class="relative">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-on-surface-variant/50 text-[18px]">domain</span>
<input class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-outline-variant bg-surface-bright focus-academic text-body-md" id="institution" name="institution" type="text" value="{{ old('institution', $user->institution) }}"/>
</div>
<x-field-error name="institution"/>
</div>
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="field_of_study">Primary Field of Study</label>
<div class="relative">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-on-surface-variant/50 text-[18px]">science</span>
<input class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-outline-variant bg-surface-bright focus-academic text-body-md" id="field_of_study" name="field_of_study" type="text" value="{{ old('field_of_study', $user->field_of_study) }}"/>
</div>
<x-field-error name="field_of_study"/>
</div>
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="role">Role</label>
<select class="{{ $input }} appearance-none" id="role" name="role">
@foreach ($roles as $value => $label)
<option @selected(old('role', $user->role) === $value) value="{{ $value }}">{{ $label }}</option>
@endforeach
</select>
</div>
<div class="flex flex-col gap-2">
<label class="font-label-md text-label-md text-on-surface">Account Status</label>
<div class="flex flex-wrap gap-2">
@if ($user->hasVerifiedEmail())
<span class="px-3 py-1 rounded bg-tertiary-fixed text-on-tertiary-fixed-variant text-label-sm font-label-md flex items-center gap-1.5">
<span class="material-symbols-outlined text-[14px]">verified</span> Email verified
                                    </span>
@endif
<span class="px-3 py-1 rounded bg-secondary-fixed text-on-secondary-fixed-variant text-label-sm font-label-md flex items-center gap-1.5">
<span class="material-symbols-outlined text-[14px]">history_edu</span> {{ $user->roleLabel() }}
                                    </span>
</div>
</div>
</div>
<div class="flex flex-col gap-1.5">
<label class="font-label-md text-label-md text-on-surface" for="bio">Bio &amp; Research Interests</label>
<textarea class="w-full px-4 py-2.5 rounded-lg border border-outline-variant bg-surface-bright focus-academic text-body-md resize-none" data-counter="bio-count" id="bio" maxlength="500" name="bio" placeholder="Describe your current research projects and academic focus..." rows="6">{{ old('bio', $user->bio) }}</textarea>
<p class="text-right text-label-sm text-on-surface-variant"><span id="bio-count">{{ mb_strlen(old('bio', $user->bio ?? '')) }}</span> / 500 characters</p>
<x-field-error name="bio"/>
</div>
</div>
</section>
</div>
<!-- Form Actions -->
<div class="mt-stack-md flex items-center justify-end gap-4 border-t border-outline-variant pt-stack-md">
<a class="px-8 py-2.5 rounded border border-outline-variant text-on-surface-variant font-label-md text-label-md hover:bg-surface-container transition-colors" href="{{ route('dashboard') }}">
                    Cancel
                </a>
<button class="px-8 py-2.5 rounded bg-primary text-white font-label-md text-label-md hover:opacity-90 shadow-md transition-all active:scale-[0.98]" data-busy-text="Updating..." type="submit">
                    Save Changes
                </button>
</div>
</form>
<!-- Danger zone -->
<section class="mt-stack-lg bg-surface-container-lowest border border-error-container rounded-xl p-stack-md">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
<div>
<h3 class="font-label-md text-label-md text-error">Delete account</h3>
<p class="text-body-sm font-body-sm text-on-surface-variant mt-1">Permanently deletes your account, every project, uploaded paper, dataset and export. This cannot be undone.</p>
</div>
<button class="px-6 py-2.5 rounded border border-error text-error font-label-md text-label-md hover:bg-error/5 transition-colors whitespace-nowrap {{ $deletionErrors->any() ? 'hidden' : '' }}" data-hide-self data-toggle="#delete-panel" type="button">Delete Account</button>
</div>
<form action="{{ route('profile.destroy') }}" class="{{ $deletionErrors->any() ? '' : 'hidden' }} mt-4 flex flex-col md:flex-row md:items-start gap-3" id="delete-panel" method="POST" onsubmit="return confirm('Delete your account and all projects permanently?')">
@csrf
@method('DELETE')
<div class="grow">
<input aria-label="Password" autocomplete="current-password" class="{{ $input }}" name="password" placeholder="Enter your password to confirm" required type="password"/>
@if ($deletionErrors->has('password'))
<p class="mt-2 flex items-center gap-1 text-[12px] font-medium text-error"><span class="material-symbols-outlined text-[16px]">error</span>{{ $deletionErrors->first('password') }}</p>
@endif
</div>
<button class="px-6 py-2.5 rounded bg-error text-on-error font-label-md text-label-md hover:opacity-90 whitespace-nowrap" type="submit">Delete Permanently</button>
</form>
</section>
<!-- Informational Footer -->
<div class="mt-12 text-center text-label-sm text-on-surface-variant opacity-60">
<p>© {{ date('Y') }} ResearchHub. Your uploads are stored privately and are only available to your account.</p>
</div>
</div>
@endsection
