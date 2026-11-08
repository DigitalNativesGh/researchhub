<?php

use App\Enums\DocumentType;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\Workflow\DocumentStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = projectAt('data_submitted', $this->owner);
    $this->stranger = User::factory()->create();
});

it('hides a project and all of its files from other researchers', function () {
    $p = $this->project;
    $paper = $p->papers()->first();
    $dataset = $p->datasets()->first();
    $doc = $p->deliveredDocuments()->first();
    $this->actingAs($this->stranger);

    foreach ([
        route('projects.show', $p), route('projects.sources', $p), route('projects.data', $p), route('projects.edit', $p),
        route('papers.file', $paper), route('datasets.file', $dataset), route('deliverables.download', [$p, $doc]),
    ] as $url) {
        expect($this->get($url)->status())->toBeIn([403, 404], $url);
    }
    foreach ([
        fn () => $this->postJson(route('papers.store', $p), ['papers' => [pdfUpload()]]),
        fn () => $this->postJson(route('datasets.store', $p), ['kind' => 'quantitative']),
        fn () => $this->post(route('data.submit', $p), ['confirm' => '1']),
        fn () => $this->post(route('projects.respond', $p), ['reply' => 'x']),
        fn () => $this->post(route('projects.submit', $p), ['confirm' => '1']),
        fn () => $this->delete(route('papers.destroy', $paper)),
        fn () => $this->delete(route('datasets.destroy', $dataset)),
        fn () => $this->delete(route('projects.destroy', $p)),
    ] as $request) {
        expect($request()->status())->toBeIn([403, 404]);
    }
    expect($p->papers()->count())->toBe(3)->and($p->datasets()->count())->toBe(1);
});

it('lets the owner reach their own files and delivered documents', function () {
    $this->actingAs($this->owner);

    $this->get(route('papers.file', $this->project->papers()->first()))->assertOk();
    $this->get(route('datasets.file', $this->project->datasets()->first()))->assertOk();
    $this->get(route('deliverables.download', [$this->project, $this->project->deliveredDocuments()->first()]))->assertOk();
});

it('forbids every admin route to researchers', function () {
    $this->actingAs($this->owner);
    $p = $this->project;
    $doc = $p->documents()->first();

    foreach ([
        route('admin.dashboard'), route('admin.queue'), route('admin.projects.index'), route('admin.projects.show', $p),
        route('admin.workflow.sources', $p), route('admin.workflow.data', $p), route('admin.workflow.paper', [$p, $p->papers()->first()]),
        route('admin.workflow.dataset', [$p, $p->datasets()->first()]), route('admin.workflow.download', [$p, $doc]),
        route('admin.users.index'), route('admin.settings'), route('admin.usage'), route('admin.payments.index'),
    ] as $url) {
        $this->get($url)->assertForbidden();
    }
    foreach (['in-progress', 'upload', 'deliver', 'request-info', 'hold', 'resume'] as $action) {
        $this->post(route("admin.workflow.{$action}", $p), ['stage' => 3, 'confirm' => '1', 'message' => 'x'])->assertForbidden();
    }
});

it('keeps undelivered versions away from the researcher until delivery', function () {
    $admin = User::where('is_admin', true)->first();
    $pending = app(DocumentStore::class)->storeDeliverable($this->project, DocumentType::Thesis, 'Thesis awaiting delivery', docxUpload('t.docx'), $admin);
    $this->actingAs($this->owner);

    $this->get(route('deliverables.download', [$this->project, $pending]))->assertNotFound();
    $this->get(route('projects.show', $this->project))->assertDontSee('Thesis awaiting delivery');
});

it('never serves legacy AI drafts, to researchers or admins', function () {
    Storage::disk(config('researchhub.disk'))->put('documents/legacy.docx', 'old draft');
    $draft = $this->project->documents()->create([
        'stage' => 1, 'type' => DocumentType::Chapters, 'slot' => 'chapters_1_3', 'label' => 'AI draft', 'kind' => ProjectDocument::AI_DRAFT,
        'version' => 1, 'format' => 'docx', 'file_path' => 'documents/legacy.docx', 'original_name' => 'legacy.docx', 'file_size' => 9, 'sha256' => str_repeat('a', 64), 'delivered_at' => now(),
    ]);

    $this->actingAs($this->owner)->get(route('deliverables.download', [$this->project, $draft]))->assertNotFound();
    $this->get(route('projects.show', $this->project))->assertDontSee('AI draft');

    $admin = User::where('is_admin', true)->first();
    $this->actingAs($admin)->get(route('admin.workflow.download', [$this->project, $draft]))->assertNotFound();
    expect($this->get(route('deliverables.download', [$this->project, $draft]))->status())->toBeIn([403, 404]);
    $this->get(route('admin.projects.show', $this->project))->assertDontSee('AI draft');

    expect(ProjectDocument::find($draft->id))->not->toBeNull(); // kept, not deleted
});

it('stores every file on a private disk with no public URL', function () {
    expect(config('filesystems.disks.'.config('researchhub.disk').'.serve'))->toBeFalse()
        ->and(config('filesystems.disks.'.config('researchhub.disk').'.root'))->toEndWith('app/private');

    $paths = $this->project->papers()->pluck('file_path')->merge($this->project->datasets()->pluck('file_path'))->merge($this->project->documents()->pluck('file_path'));
    foreach ($paths as $path) {
        $this->get('/storage/'.$path)->assertNotFound();
        expect(file_exists(public_path($path)))->toBeFalse();
    }
});

it('requires sign-in for everything', function () {
    $this->get(route('projects.show', $this->project))->assertRedirect(route('login'));
    $this->get(route('title-assistant'))->assertRedirect(route('login'));
    $this->get(route('admin.queue'))->assertRedirect(route('login'));
});
