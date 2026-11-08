<?php

use App\Models\User;
use App\Notifications\Admin\DataSubmitted;
use App\Notifications\Admin\ProjectSubmitted;
use App\Notifications\Researcher\DataReceived;
use App\Notifications\Researcher\ProjectReceived;
use App\Notifications\Researcher\Stage1Delivered;
use App\Notifications\Researcher\ThesisDelivered;
use Illuminate\Support\Facades\Notification;

it('sends the six workflow notifications to the right people', function () {
    Notification::fake();
    $admin = admin();
    $project = projectAt('completed');
    $researcher = $project->user;

    Notification::assertSentTo($admin, ProjectSubmitted::class, 1);
    Notification::assertSentTo($admin, DataSubmitted::class, 1);
    Notification::assertSentTo($researcher, ProjectReceived::class, 1);
    Notification::assertSentTo($researcher, Stage1Delivered::class, 1);
    Notification::assertSentTo($researcher, DataReceived::class, 1);
    Notification::assertSentTo($researcher, ThesisDelivered::class, 1);

    Notification::assertNotSentTo($researcher, ProjectSubmitted::class);
    Notification::assertNotSentTo($admin, ThesisDelivered::class);
});

it('emails only people who opted in and verified their address, and always keeps an in-app copy', function () {
    $project = projectAt('submitted');
    $on = User::factory()->create(['email_alerts' => true]);
    $off = User::factory()->create(['email_alerts' => false]);
    $unverified = User::factory()->unverified()->create(['email_alerts' => true]);
    $note = new ProjectReceived($project);

    expect($note->via($on))->toBe(['database', 'mail'])
        ->and($note->via($off))->toBe(['database'])
        ->and($note->via($unverified))->toBe(['database']);
});

it('stores in-app notifications the researcher can open', function () {
    $project = projectAt('stage1_delivered');

    $titles = $project->user->notifications()->get()->pluck('data.title')->all();
    expect($titles)->toContain('Your Chapters 1–3 and instrument are ready');
});

it('never puts internal details in researcher notifications', function () {
    $project = projectAt('completed');
    $project->user->forceFill(['email_alerts' => true])->save();

    foreach ([new ProjectReceived($project), new Stage1Delivered($project), new DataReceived($project), new ThesisDelivered($project)] as $note) {
        $text = json_encode($note->toArray($project->user)).implode(' ', $note->toMail($project->user)->introLines);
        expect($text)->not->toContain('Esi Writer')->not->toContain('Kofi Writer')->not->toContain('Internal note')
            ->not->toMatch('/\bAI\b|generat/i');
    }
});
