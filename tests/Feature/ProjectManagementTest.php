<?php

use App\Enums\WorkflowStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('validates the project details step', function () {
    $this->post(route('projects.store'), ['title' => 'Short', 'discipline' => 'Astrology'])
        ->assertSessionHasErrors(['title', 'discipline', 'degree_level', 'research_type', 'citation_style']);
});

it('saves a draft and continues to sources', function () {
    $data = ['title' => 'A sufficiently long project title', 'discipline' => 'Education', 'degree_level' => 'phd', 'research_type' => 'mixed', 'citation_style' => 'harvard'];
    paidFor($this->user, 'phd', 2);

    $this->post(route('projects.store'), $data + ['intent' => 'draft'])->assertRedirect(route('projects.index'));
    $this->post(route('projects.store'), $data)->assertRedirect(route('projects.setup.sources', Project::latest('id')->first()));

    expect($this->user->projects()->count())->toBe(2);
});

it('lists, filters and searches projects', function () {
    Project::factory()->for($this->user)->create(['title' => 'Mobile learning in Ghana']);
    Project::factory()->for($this->user)->workflow(WorkflowStatus::Stage1Delivered)->create(['title' => 'Nursing workloads study']);
    Project::factory()->create(['title' => 'Somebody else entirely']);

    $this->get(route('projects.index'))->assertOk()->assertSee('Mobile learning in Ghana')->assertDontSee('Somebody else');
    $this->get(route('projects.index', ['q' => 'nursing']))->assertSee('Nursing workloads')->assertDontSee('Mobile learning in Ghana');
    $this->get(route('projects.index', ['status' => 'review']))->assertSee('Nursing workloads')->assertDontSee('Mobile learning in Ghana');
    $this->get(route('dashboard'))->assertOk()->assertSee('Nursing workloads');
});

it('shows an empty state for new users', function () {
    $this->get(route('projects.index'))->assertOk()->assertSee(route('projects.new'));
});

it('fixes the project details once the project has started', function () {
    $project = projectAt('stage1_awaiting_editing', $this->user);

    $this->get(route('projects.edit', $project))->assertRedirect(route('projects.show', $project));
    $this->put(route('projects.update', $project), [
        'title' => 'A completely different project title', 'discipline' => 'Education', 'degree_level' => 'mphil', 'research_type' => 'qualitative', 'citation_style' => 'apa7',
    ])->assertSessionHas('error');

    expect($project->fresh())->research_type->value->toBe('quantitative')->title->not->toBe('A completely different project title');
});

it('deletes a draft project with its files but not one being prepared', function () {
    $project = projectAt('draft', $this->user);
    $path = $project->papers()->first()->file_path;

    $this->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

    expect(Project::find($project->id))->toBeNull()
        ->and(Storage::disk(config('researchhub.disk'))->exists($path))->toBeFalse();

    $busy = projectAt('submitted', $this->user);
    $this->delete(route('projects.destroy', $busy))->assertSessionHas('error');
    expect(Project::find($busy->id))->not->toBeNull();
});

it('renders every researcher page at every stage', function (string $stage) {
    $project = projectAt($stage, $this->user);

    foreach (['projects.show', 'projects.sources', 'projects.data', 'projects.progress'] as $route) {
        $this->get(route($route, $project))->assertOk();
    }
})->with(['draft', 'stage1_awaiting_editing', 'stage1_delivered', 'stage3_awaiting_editing', 'completed']);

it('renders the setup pages of a draft project', function (string $route) {
    $project = Project::factory()->for($this->user)->create();

    $this->get(route($route, $project))->assertOk();
})->with(['projects.show', 'projects.setup.sources', 'projects.setup.review', 'projects.edit', 'projects.sources']);

it('asks for the research type before the project details', function () {
    $this->get(route('projects.create'))->assertRedirect(route('projects.new'));
    $this->get(route('title-assistant'))->assertRedirect(route('projects.new'));
    $this->get(route('projects.new'))->assertOk()->assertSee('What type of research are you working on?')->assertSee('MBA Research')->assertSee('PhD Thesis');

    $this->post(route('projects.new.store'), [])->assertSessionHasErrors('degree_level');
    $this->post(route('projects.new.store'), ['degree_level' => 'mba'])->assertRedirect(route('projects.payment'));
    paidFor($this->user, 'mba');

    $this->get(route('projects.create'))->assertOk()->assertSee('MBA Research')->assertSee('name="degree_level" type="hidden" value="mba"', false);
    $this->get(route('title-assistant'))->assertOk()->assertSee('MBA Research');
});
