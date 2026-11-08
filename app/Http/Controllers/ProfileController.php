<?php

namespace App\Http\Controllers;

use App\Models\User;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]{5,40}$/'],
            'timezone' => ['required', 'timezone:all'],
            'role' => ['nullable', Rule::in(array_keys(User::ROLES))],
            'institution' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'email_alerts' => ['boolean'],
            'push_notifications' => ['boolean'],
        ], ['phone.regex' => 'Enter a valid phone number.']);

        $data['email_alerts'] = $request->boolean('email_alerts');
        $data['push_notifications'] = $request->boolean('push_notifications');

        $user->fill($data);
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'Profile saved. Please verify your new email address.');
        }

        return back()->with('status', 'Profile saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password']), 'password_changed_at' => now()])->save();
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', 'Password changed. Other devices have been signed out.');
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ], ['photo.max' => 'The photo may not be larger than 2 MB.']);

        $user = $request->user();
        $user->deleteAvatar();
        $path = $request->file('photo')->store('avatars', config('researchhub.disk'));
        $user->forceFill(['avatar_path' => $path])->save();

        return back()->with('status', 'Photo updated.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->deleteAvatar();
        $user->forceFill(['avatar_path' => null])->save();

        return back()->with('status', 'Photo removed.');
    }

    /** Serve the signed-in user's photo from the private disk. */
    public function photo(Request $request): StreamedResponse
    {
        $user = $request->user();
        $disk = Storage::disk(config('researchhub.disk'));
        abort_unless($user->avatar_path && $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('deletion', ['password' => ['required', 'current_password']]);

        $user = $request->user();
        foreach ($user->projects as $project) {
            $project->purgeFiles();
            $project->delete();
        }

        $user->deleteAvatar();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Your account and all its projects have been deleted.');
    }
}
