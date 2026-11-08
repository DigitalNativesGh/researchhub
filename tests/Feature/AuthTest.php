<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('registers a user and asks them to verify their email', function () {
    Notification::fake();

    $this->post('/register', ['name' => 'Ama Mensah', 'email' => 'ama@example.com', 'password' => 'a-Strong-pass-123', 'terms' => '1'])
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'ama@example.com')->sole();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('requires accepting the terms', function () {
    $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'a-Strong-pass-123'])
        ->assertSessionHasErrors('terms');
});

it('keeps unverified users out of the app', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

it('verifies the email and sends new users to role onboarding', function () {
    $user = User::factory()->unverified()->withoutRole()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertRedirect(route('onboarding'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->post(route('onboarding.store'), ['role' => 'student'])->assertRedirect(route('dashboard'));
    expect($user->fresh()->role)->toBe('student');
});

it('logs in and out', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('sends and accepts password reset links', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token, 'email' => $user->email,
            'password' => 'New-strong-pass-456', 'password_confirmation' => 'New-strong-pass-456',
        ])->assertRedirect(route('dashboard'));

        return true;
    });

    $this->post(route('logout'));
    $this->post('/login', ['email' => $user->email, 'password' => 'New-strong-pass-456'])->assertRedirect(route('dashboard'));
});

it('renders the public pages', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/', '/login', '/register', '/forgot-password']);

it('redirects guests to the login page', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});
