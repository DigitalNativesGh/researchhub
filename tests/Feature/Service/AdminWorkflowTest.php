<?php

use App\Enums\WorkflowStatus as W;
use App\Models\Dataset;
use App\Models\ProjectAuditLog;
use App\Services\Workflow\ProjectWorkflow;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    $this->admin = admin();
    $this->actingAs($this->admin);
});

function zipEntries(BinaryFileResponse $response): array
{
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();
    sort($names);

    return $names;
}

it('lists waiting projects oldest first with waiting time and deadlines, and filters them', function () {
    $old = projectAt('submitted', null, ['title' => 'Old waiting project', 'deadline' => now()->subDays(2)->toDateString()]);
    $old->forceFill(['workflow_changed_at' => now()->subDays(4)])->save();
    $phd = projectAt('data_submitted', null, ['title' => 'PhD data project', 'degree_level' => 'phd', 'discipline' => 'Law', 'deadline' => now()->addDays(20)->toDateString()]);
    projectAt('needs_info', null, ['title' => 'Paused for info']);
    projectAt('draft', null, ['title' => 'Still a draft']);

    $this->get(route('admin.queue'))->assertOk()
        ->assertSeeInOrder(['Old waiting project', 'PhD data project'])
        ->assertSee('4 days')->assertSee('2 days overdue')
        ->assertDontSee('Paused for info')->assertDontSee('Still a draft');

    $this->get(route('admin.queue', ['scope' => 'all']))->assertSee('Paused for info');
    $this->get(route('admin.queue', ['stage' => 3]))->assertSee('PhD data project')->assertDontSee('Old waiting project');
    $this->get(route('admin.queue', ['status' => 'submitted']))->assertSee('Old waiting project')->assertDontSee('PhD data project');
    $this->get(route('admin.queue', ['level' => 'phd']))->assertSee('PhD data project')->assertDontSee('Old waiting project');
    $this->get(route('admin.queue', ['discipline' => 'Law']))->assertSee('PhD data project')->assertDontSee('Old waiting project');
    $this->get(route('admin.queue', ['deadline' => 'overdue']))->assertSee('Old waiting project')->assertDontSee('PhD data project');
    $this->get(route('admin.queue', ['deadline' => '30']))->assertSee('PhD data project')->assertDontSee('Old waiting project');
});

it('shows the full submission on the detail page, including where the title came from', function () {
    $project = projectAt('submitted', null, ['title_source' => 'suggested_edited', 'guidelines' => 'Use Harvard.', 'writer_notes' => 'Keep it short.']);
    addPapers($project, ['Template.pdf'], 'guidelines');

    $this->get(route('admin.projects.show', $project))->assertOk()
        ->assertSee($project->title)->assertSee($project->reference())->assertSee($project->titleSourceLabel())
        ->assertSee('Use Harvard.')->assertSee('Keep it short.')->assertSee('Owusu_2021_adoption.pdf')->assertSee('Template.pdf');
});

it('downloads single sources and a ZIP named with the reference, and audits it', function () {
    $project = projectAt('submitted');
    addPapers($project, ['Template.pdf'], 'guidelines');
    $copy = $project->papers()->where('original_name', 'Owusu_2021_adoption.pdf')->first()->replicate();
    $copy->sha256 = hash('sha256', 'a different file with the same name');
    $copy->save();

    $this->get(route('admin.workflow.paper', [$project, $project->papers()->first()]))->assertOk()->assertDownload('Owusu_2021_adoption.pdf');

    $response = $this->get(route('admin.workflow.sources', $project))->assertOk()->assertDownload($project->reference().'_sources.zip');
    expect(zipEntries($response->baseResponse))->toBe([
        'Bello_2023_connectivity.pdf', 'Guidelines/Template.pdf', 'Nkrumah_2022_performance.pdf', 'Owusu_2021_adoption (2).pdf', 'Owusu_2021_adoption.pdf',
    ]);
    expect(ProjectAuditLog::where('action', 'sources.downloaded')->sole()->user_id)->toBe($this->admin->id);

    $other = projectAt('submitted');
    $this->get(route('admin.workflow.paper', [$project, $other->papers()->first()]))->assertNotFound();
});

it('downloads only submitted data, singly or as a ZIP', function () {
    $project = projectAt('stage1_delivered');
    uploadData($project, Dataset::KIND_QUANTITATIVE, 'responses.csv', responsesCsv());
    $this->get(route('admin.workflow.data', $project))->assertNotFound();

    app(ProjectWorkflow::class)->submitData($project->fresh(), $project->user);
    $dataset = $project->datasets()->sole();

    $this->get(route('admin.workflow.dataset', [$project, $dataset]))->assertOk()->assertDownload('responses.csv');
    $response = $this->get(route('admin.workflow.data', $project))->assertOk()->assertDownload($project->reference().'_data.zip');
    expect(zipEntries($response->baseResponse))->toBe(['responses.csv']);
});

it('marks work in progress with internal details the researcher never sees', function () {
    $project = projectAt('submitted');

    $this->post(route('admin.workflow.in-progress', $project), ['writer_name' => 'Yaw Secret-Writer', 'expected_at' => now()->addDays(9)->toDateString(), 'notes' => 'Internal: tight deadline'])->assertSessionHas('status');

    expect($project)->toBeAt(W::Stage1InProgress);
    $assignment = $project->editingAssignments()->latest('id')->first();
    expect($assignment->editor_name)->toBe('Yaw Secret-Writer')->and($assignment->notes)->toBe('Internal: tight deadline');
    $this->get(route('admin.projects.show', $project))->assertSee('Yaw Secret-Writer');

    $this->actingAs($project->user);
    foreach ([route('projects.show', $project), route('projects.sources', $project), route('projects.index'), route('dashboard')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Yaw Secret-Writer')->assertDontSee('Internal: tight deadline');
    }
});

it('accepts deliverables only in the allowed formats with matching contents', function () {
    $project = projectAt('stage1_in_progress');
    $url = route('admin.workflow.upload', $project);

    $this->post($url, ['type' => 'chapters_1_3', 'file' => UploadedFile::fake()->createWithContent('ch.txt', 'text')])->assertSessionHasErrors('file');
    $this->post($url, ['type' => 'chapters_1_3', 'file' => UploadedFile::fake()->createWithContent('ch.docx', 'not a zip')])->assertSessionHasErrors('file');
    $this->post($url, ['type' => 'chapters_1_3', 'file' => pdfUpload('chapters.pdf')])->assertSessionHas('status');
    $this->post($url, ['type' => 'instrument', 'instrument' => 'Interview guide', 'file' => docxUpload('guide.docx')])->assertSessionHas('status');

    expect($project->documents()->pluck('format', 'label')->all())->toBe(['Chapters 1–3' => 'pdf', 'Interview guide' => 'docx']);
});

it('never overwrites: every upload is a new version and old files stay', function () {
    $project = projectAt('stage1_in_progress');
    foreach (['v1', 'v2'] as $v) {
        $this->post(route('admin.workflow.upload', $project), ['type' => 'chapters_1_3', 'file' => docxUpload("chapters-{$v}.docx", [$v])]);
    }

    $docs = $project->documents()->where('type', 'chapters_1_3')->orderBy('version')->get();
    expect($docs->pluck('version')->all())->toBe([1, 2])
        ->and($docs[0]->file_path)->not->toBe($docs[1]->file_path)
        ->and(Storage::disk(config('researchhub.disk'))->exists($docs[0]->file_path))->toBeTrue();

    $this->get(route('admin.workflow.download', [$project, $docs[0]]))->assertDownload($project->reference().'_chapters_1_3_v1.docx');
});

it('needs a confirmation and the required documents before delivering stage 1', function () {
    $project = projectAt('stage1_in_progress');
    $deliver = route('admin.workflow.deliver', $project);

    $this->post(route('admin.workflow.upload', $project), ['type' => 'chapters_1_3', 'file' => docxUpload('ch.docx')]);
    $this->post($deliver, ['stage' => 1, 'confirm' => '1'])->assertSessionHas('error');
    expect($project)->toBeAt(W::Stage1InProgress);

    $this->post(route('admin.workflow.upload', $project), ['type' => 'instrument', 'instrument' => 'Questionnaire', 'file' => docxUpload('q.docx')]);
    $this->post($deliver, ['stage' => 1])->assertSessionHasErrors('confirm');
    $this->post($deliver, ['stage' => 1, 'confirm' => '1'])->assertSessionHas('status');

    expect($project)->toBeAt(W::Stage1Delivered)
        ->and($project->documents()->whereNull('delivered_at')->count())->toBe(0)
        ->and(ProjectAuditLog::where('action', 'document.delivered')->count())->toBe(2);
});

it('delivers corrections after delivery without changing the stage', function () {
    $project = projectAt('stage1_delivered');
    $this->post(route('admin.workflow.upload', $project), ['type' => 'chapters_1_3', 'file' => docxUpload('fixed.docx', ['Corrected'])]);
    $this->post(route('admin.workflow.deliver', $project), ['stage' => 1, 'confirm' => '1'])->assertSessionHas('status');

    expect($project)->toBeAt(W::Stage1Delivered)
        ->and($project->documents()->where('type', 'chapters_1_3')->whereNotNull('delivered_at')->count())->toBe(2);
});

it('accepts the complete thesis only after data is submitted', function () {
    $project = projectAt('stage1_delivered');
    $this->post(route('admin.workflow.upload', $project), ['type' => 'complete_thesis', 'file' => docxUpload('thesis.docx')])->assertSessionHas('error');
    $this->post(route('admin.workflow.deliver', $project), ['stage' => 3, 'confirm' => '1'])->assertSessionHas('error');

    $project = projectAt('data_submitted');
    $this->post(route('admin.workflow.upload', $project), ['type' => 'complete_thesis', 'file' => docxUpload('thesis.docx')])->assertSessionHas('status');
    $this->post(route('admin.workflow.deliver', $project), ['stage' => 3, 'confirm' => '1'])->assertSessionHas('status');
    expect($project)->toBeAt(W::Completed);
});

it('asks for information, holds and resumes from the detail page', function () {
    $project = projectAt('submitted');

    $this->post(route('admin.workflow.request-info', $project), [])->assertSessionHasErrors('message');
    $this->post(route('admin.workflow.request-info', $project), ['message' => 'Which campus?'])->assertSessionHas('status');
    expect($project)->toBeAt(W::NeedsInfo)->and($project->fresh()->info_request)->toBe('Which campus?');

    $this->post(route('admin.workflow.hold', $project))->assertSessionHas('error'); // already waiting on the researcher
    app(ProjectWorkflow::class)->provideInfo($project->fresh(), $project->user, 'Main campus.');

    $this->post(route('admin.workflow.hold', $project), ['reason' => 'x'])->assertSessionHas('status');
    expect($project)->toBeAt(W::OnHold);
    $this->post(route('admin.workflow.resume', $project))->assertSessionHas('status');
    expect($project)->toBeAt(W::Submitted);

    $this->post(route('admin.workflow.resume', $project))->assertSessionHas('error'); // not on hold
});

it('keeps an audit log of uploads, downloads and changes with the acting user', function () {
    $project = projectAt('completed');
    $this->get(route('admin.workflow.sources', $project));

    $actions = ProjectAuditLog::where('project_id', $project->id)->pluck('action')->all();
    expect($actions)->toContain('project.submitted', 'work.started', 'document.uploaded', 'document.delivered', 'data.submitted', 'sources.downloaded');
    expect(ProjectAuditLog::where('project_id', $project->id)->whereNull('user_id')->count())->toBe(0);

    $this->get(route('admin.projects.show', $project))->assertSee('Delivered Complete thesis v1 to the researcher');
});
