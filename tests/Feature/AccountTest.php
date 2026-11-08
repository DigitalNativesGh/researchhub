<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('updates the profile', function () {
    $this->put(route('profile.update'), [
        'name' => 'Dr. Ama Mensah', 'email' => $this->user->email, 'timezone' => 'Africa/Accra', 'role' => 'academic',
        'institution' => 'Demo University', 'field_of_study' => 'Education', 'bio' => 'Hello', 'email_alerts' => '0', 'push_notifications' => '1',
    ])->assertSessionHasNoErrors()->assertSessionHas('status');

    expect($this->user->fresh())
        ->name->toBe('Dr. Ama Mensah')->timezone->toBe('Africa/Accra')->email_alerts->toBeFalse()->push_notifications->toBeTrue()
        ->and($this->user->fresh()->firstName())->toBe('Dr. Ama');
});

it('asks for verification again when the email changes', function () {
    Notification::fake();

    $this->put(route('profile.update'), ['name' => 'X', 'email' => 'new@example.com', 'timezone' => 'UTC'])
        ->assertRedirect(route('verification.notice'));

    expect($this->user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($this->user->fresh(), VerifyEmail::class);
});

it('changes the password only with the current one', function () {
    $this->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'New-pass-12345', 'password_confirmation' => 'New-pass-12345'])
        ->assertSessionHasErrorsIn('password', 'current_password');

    $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'New-pass-12345', 'password_confirmation' => 'New-pass-12345'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('New-pass-12345', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->fresh()->password_changed_at)->not->toBeNull();
});

it('uploads, serves and removes a profile photo privately', function () {
    $this->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('me.jpg', 200, 200)])->assertSessionHasNoErrors();
    $path = $this->user->fresh()->avatar_path;
    expect(Storage::disk(config('researchhub.disk'))->exists($path))->toBeTrue();

    $this->get(route('profile.photo'))->assertOk();
    $this->delete(route('profile.photo.destroy'))->assertRedirect();
    expect(Storage::disk(config('researchhub.disk'))->exists($path))->toBeFalse();
});

it('rejects non-image photos', function () {
    $this->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');
});

it('deletes the account with every project and file', function () {
    $project = projectAt('framework_review', $this->user);
    $paths = $project->papers()->pluck('file_path');

    $this->delete(route('profile.destroy'), ['password' => 'wrong'])->assertSessionHasErrorsIn('deletion', 'password');
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

    expect(User::find($this->user->id))->toBeNull()
        ->and($project->fresh())->toBeNull();
    foreach ($paths as $path) {
        expect(Storage::disk(config('researchhub.disk'))->exists($path))->toBeFalse();
    }
});

it('warns admins when queued jobs are not being processed', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'ai', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(10)->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()]);
    $admin = admin();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('The queue worker is not running');
});
