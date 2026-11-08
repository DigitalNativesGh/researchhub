<?php

use App\Models\AdminLog;
use App\Models\AiCall;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->admin->forceFill(['is_admin' => true])->save();
});

it('keeps non-administrators out', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('renders every admin page', function (string $route) {
    $owner = User::factory()->create();
    $project = projectAt('complete', $owner);
    AiCall::create(['project_id' => $project->id, 'user_id' => $owner->id, 'stage' => 'write_section', 'model' => 'claude-opus-5-5', 'input_tokens' => 100, 'output_tokens' => 50, 'cost' => 0.25, 'status' => 'success']);
    DB::table('failed_jobs')->insert(['uuid' => 'abc-123', 'connection' => 'redis', 'queue' => 'ai', 'payload' => json_encode(['displayName' => 'App\\Jobs\\WriteChapter']), 'exception' => "RuntimeException: boom\n#0 trace", 'failed_at' => now()]);

    $params = match ($route) {
        'admin.users.show' => [$owner],
        'admin.projects.show' => [$project],
        default => [],
    };

    $this->actingAs($this->admin)->get(route($route, $params))->assertOk();
})->with(['admin.dashboard', 'admin.users.index', 'admin.users.show', 'admin.projects.index', 'admin.projects.show', 'admin.usage', 'admin.jobs.index', 'admin.activity', 'admin.settings']);

it('shows the admin link only to administrators', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('admin.dashboard'));
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee(route('admin.dashboard'));
});

it('suspends and restores a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.suspend', $user), ['reason' => 'Abuse'])->assertSessionHas('status');
    expect($user->fresh()->isSuspended())->toBeTrue();

    // An open session is ended on the next request, and signing in is refused.
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($this->admin)->post(route('admin.users.unsuspend', $user));
    expect($user->fresh()->isSuspended())->toBeFalse();
    expect(AdminLog::pluck('action')->all())->toBe(['user.suspended', 'user.unsuspended']);
});

it('promotes, demotes and verifies users but never changes itself', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($this->admin)->post(route('admin.users.admin', $user));
    expect($user->fresh()->is_admin)->toBeTrue();
    $this->post(route('admin.users.admin', $user));
    expect($user->fresh()->is_admin)->toBeFalse();

    $this->post(route('admin.users.verify', $user));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->post(route('admin.users.admin', $this->admin))->assertSessionHas('error');
    $this->post(route('admin.users.suspend', $this->admin))->assertSessionHas('error');
    expect($this->admin->fresh()->is_admin)->toBeTrue()->and($this->admin->fresh()->isSuspended())->toBeFalse();
});

it('deletes a user and their projects after confirmation', function () {
    $user = User::factory()->create();
    $project = projectAt('framework_review', $user);
    $path = $project->papers()->first()->file_path;

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user), ['confirm_email' => 'wrong@example.com'])->assertSessionHasErrors('confirm_email');
    expect(User::find($user->id))->not->toBeNull();

    $this->delete(route('admin.users.destroy', $user), ['confirm_email' => $user->email])->assertRedirect(route('admin.users.index'));
    expect(User::find($user->id))->toBeNull()
        ->and(Project::find($project->id))->toBeNull()
        ->and(Storage::disk(config('researchhub.disk'))->exists($path))->toBeFalse();
});

it('lists and deletes any project', function () {
    $project = Project::factory()->create(['title' => 'Somebody else entirely different']);

    $this->actingAs($this->admin)->get(route('admin.projects.index', ['q' => 'somebody']))->assertSee('Somebody else entirely different');
    $this->get(route('admin.projects.index', ['status' => 'completed']))->assertDontSee('Somebody else entirely different');

    $this->delete(route('admin.projects.destroy', $project), ['confirm' => $project->reference()])->assertRedirect(route('admin.projects.index'));
    expect(Project::find($project->id))->toBeNull();
});

it('retries and discards failed jobs', function () {
    DB::table('failed_jobs')->insert(['uuid' => 'job-1', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'X', 'uuid' => 'job-1', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => ['command' => '']]), 'exception' => 'boom', 'failed_at' => now()]);
    DB::table('failed_jobs')->insert(['uuid' => 'job-2', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);

    $this->actingAs($this->admin)->delete(route('admin.jobs.destroy', 'job-2'))->assertSessionHas('status');
    expect(DB::table('failed_jobs')->count())->toBe(1);

    $this->delete(route('admin.jobs.flush'));
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('saves platform settings that take effect', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'announcement' => 'Maintenance tonight at 22:00', 'announcement_level' => 'warning',
        'title_suggestions_per_hour' => 3, 'paper_max_count' => 25, 'min_sources' => 2,
        'price_undergraduate' => 300, 'price_mba' => 600, 'price_mphil' => 800, 'price_phd' => 1500,
    ])->assertSessionHasNoErrors();

    expect(Setting::get('registration_open'))->toBeFalse()
        ->and(config('researchhub.rate_limits.title_suggestions_per_hour'))->toBe(3)
        ->and(config('researchhub.uploads.paper_max_count'))->toBe(25);

    $this->get(route('dashboard'))->assertSee('Maintenance tonight at 22:00');

    auth()->logout();
    $this->get(route('register'))->assertRedirect(route('login'));
    $this->post(route('register'), ['name' => 'New', 'email' => 'new@example.com', 'password' => 'Password123!', 'terms' => '1'])->assertForbidden();
});

it('grants administrator access from the command line', function () {
    $user = User::factory()->create(['email' => 'boss@example.com']);

    $this->artisan('researchhub:admin', ['email' => 'boss@example.com'])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('researchhub:admin', ['email' => 'boss@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeFalse();

    $this->artisan('researchhub:admin', ['email' => 'nobody@example.com'])->assertFailed();
});
