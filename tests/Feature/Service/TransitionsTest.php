<?php

use App\Enums\WorkflowStatus as W;
use App\Models\Project;
use App\Models\ProjectTransition;
use App\Services\Workflow\InvalidTransition;
use App\Services\Workflow\ProjectWorkflow;

it('allows only the documented transitions', function () {
    $resumable = ['submitted', 'stage1_in_progress', 'stage1_delivered', 'data_submitted', 'stage3_in_progress'];
    $allowed = [
        'draft' => ['submitted'],
        'submitted' => ['stage1_in_progress', 'stage1_delivered', 'needs_info', 'on_hold'],
        'stage1_in_progress' => ['submitted', 'stage1_delivered', 'needs_info', 'on_hold'],
        'stage1_delivered' => ['data_submitted', 'on_hold'],
        'data_submitted' => ['stage3_in_progress', 'completed', 'needs_info', 'on_hold'],
        'stage3_in_progress' => ['data_submitted', 'completed', 'needs_info', 'on_hold'],
        'completed' => [],
        'needs_info' => $resumable,
        'on_hold' => $resumable,
    ];

    foreach (W::cases() as $from) {
        foreach (W::cases() as $to) {
            expect($from->canMoveTo($to))->toBe(in_array($to->value, $allowed[$from->value], true), "{$from->value} → {$to->value}");
        }
    }
});

it('refuses invalid moves and leaves the project untouched', function () {
    $project = Project::factory()->create();
    $workflow = app(ProjectWorkflow::class);

    expect(fn () => $workflow->transition($project, W::Completed))->toThrow(InvalidTransition::class)
        ->and(fn () => $workflow->transition($project, W::Stage1Delivered))->toThrow(InvalidTransition::class);
    expect($project->fresh()->workflow_status)->toBe(W::Draft)->and(ProjectTransition::count())->toBe(0);
});

it('records every transition with a timestamp and the acting user', function () {
    $project = projectAt('completed');

    $transitions = $project->transitions()->with('user')->get();
    expect($transitions->pluck('to_status')->all())->toBe(['submitted', 'stage1_in_progress', 'stage1_delivered', 'data_submitted', 'stage3_in_progress', 'completed'])
        ->and($transitions->every(fn ($t) => $t->created_at !== null))->toBeTrue()
        ->and($transitions[0]->user_id)->toBe($project->user_id)   // researcher submitted
        ->and($transitions[1]->user->is_admin)->toBeTrue()          // admin started work
        ->and($transitions[3]->user_id)->toBe($project->user_id)   // researcher submitted data
        ->and($transitions[5]->user->is_admin)->toBeTrue()
        ->and($project->completed_at)->not->toBeNull();
});

it('returns from "needs information" and "on hold" to where the project was', function (string $at, W $expected) {
    $project = projectAt($at);
    $admin = admin();
    $workflow = app(ProjectWorkflow::class);

    $workflow->hold($project, $admin, 'Paused');
    expect($project)->toBeAt(W::OnHold)->and($project->fresh()->resume_status)->toBe($expected->value);
    $workflow->resume($project->fresh(), $admin);
    expect($project)->toBeAt($expected)->and($project->fresh()->resume_status)->toBeNull();

    if ($expected->needsAdmin()) {
        $workflow->requestInfo($project->fresh(), $admin, 'Question?');
        expect($project)->toBeAt(W::NeedsInfo);
        $workflow->provideInfo($project->fresh(), $project->user, 'Answer.');
        expect($project)->toBeAt($expected);
    }
})->with([
    ['submitted', W::Submitted], ['stage1_in_progress', W::Stage1InProgress], ['stage1_delivered', W::Stage1Delivered],
    ['data_submitted', W::DataSubmitted], ['stage3_in_progress', W::Stage3InProgress],
]);

it('cannot skip ahead or go backwards from the researcher side', function () {
    $project = projectAt('submitted');
    $this->actingAs($project->user);

    $this->post(route('data.submit', $project), ['confirm' => '1'])->assertSessionHas('error');
    $this->post(route('projects.respond', $project))->assertRedirect(route('projects.show', $project));

    expect($project)->toBeAt(W::Submitted);
});
