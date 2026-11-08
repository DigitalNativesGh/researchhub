<?php

use App\Http\Controllers\PaperController;
use App\Models\Project;
use App\Support\UploadLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    fakeClaude();
    $this->project = Project::factory()->create();
    $this->actingAs($this->project->user);
});

it('stores PDFs privately and skips duplicates', function () {
    $file = pdfUpload('Owusu_2021_a.pdf');

    $this->post(route('papers.store', $this->project), ['papers' => [$file]])->assertSessionHasNoErrors();
    $this->post(route('papers.store', $this->project), ['papers' => [$file]])->assertSessionHasErrors('papers');

    $paper = $this->project->papers()->sole();
    expect(Storage::disk(config('researchhub.disk'))->exists($paper->file_path))->toBeTrue()
        ->and($paper->file_path)->not->toStartWith('public');
});

it('rejects files that are not PDFs even when renamed', function () {
    $fake = UploadedFile::fake()->createWithContent('paper.pdf', 'PK this is a zip file');

    $this->post(route('papers.store', $this->project), ['papers' => [$fake]])->assertSessionHasErrors('papers');
    expect($this->project->papers()->count())->toBe(0);
});

it('rejects PDFs over the size limit', function () {
    config(['researchhub.uploads.paper_max_kb' => 1]);
    $big = UploadedFile::fake()->create('Big_2020.pdf', 50, 'application/pdf');

    $this->post(route('papers.store', $this->project), ['papers' => [$big]])->assertSessionHasErrors('papers.0');
});

it('enforces the per-project paper limit', function () {
    config(['researchhub.uploads.paper_max_count' => 2]);

    $this->post(route('papers.store', $this->project), ['papers' => [pdfUpload('A_2020.pdf'), pdfUpload('B_2021.pdf', 3)]])->assertSessionHasNoErrors();
    $this->post(route('papers.store', $this->project), ['papers' => [pdfUpload('C_2022.pdf', 4)]])->assertSessionHasErrors('papers');
    $this->post(route('papers.store', $this->project), ['papers' => [pdfUpload('D_2022.pdf', 5), pdfUpload('E_2022.pdf', 6), pdfUpload('F_2022.pdf', 7)]])->assertSessionHasErrors('papers');

    expect($this->project->papers()->count())->toBe(2);
});

it('fixes the sources once the project has started', function () {
    $project = projectAt('stage1_awaiting_editing');
    $this->actingAs($project->user);
    $paper = $project->papers()->first();

    $this->post(route('papers.store', $project), ['papers' => [pdfUpload('Late_2024_extra.pdf')]])->assertSessionHas('error');
    $this->delete(route('papers.destroy', $paper))->assertSessionHas('error');

    expect($project->papers()->count())->toBe(3)
        ->and($project->papers()->where('original_name', 'Late_2024_extra.pdf')->exists())->toBeFalse();
    $this->get(route('projects.sources', $project))->assertOk()->assertDontSee('Browse Files');
});

it('answers an upload that is too large for the server with a message instead of crashing', function () {
    $huge = ['CONTENT_LENGTH' => 90 * 1024 * 1024, 'CONTENT_TYPE' => 'multipart/form-data; boundary=x'];

    $this->from(route('projects.setup.sources', $this->project))
        ->call('POST', route('papers.store', $this->project), [], [], [], $huge)
        ->assertRedirect(route('projects.setup.sources', $this->project))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'too large'));

    $this->call('POST', route('papers.store', $this->project), [], [], [], $huge + ['HTTP_ACCEPT' => 'application/json'])
        ->assertStatus(413)->assertJson(['message' => true]);
});

it('takes one paper per request from the upload page and reports what was rejected', function () {
    $this->postJson(route('papers.store', $this->project), ['papers' => [pdfUpload('Owusu_2021_one.pdf')], 'redirect' => 'setup'])
        ->assertCreated()->assertJson(['added' => 1, 'rejected' => []]);

    $this->postJson(route('papers.store', $this->project), ['papers' => [pdfUpload('Owusu_2021_one.pdf')]])
        ->assertStatus(422)->assertJsonPath('rejected.0', 'Owusu_2021_one.pdf is already in this project.');

    expect($this->project->papers()->count())->toBe(1);
});

it('advertises the size the server can really accept', function () {
    config(['researchhub.uploads.paper_max_kb' => 32 * 1024]);
    $limit = UploadLimits::fileKb(32 * 1024);

    expect($limit)->toBeLessThanOrEqual(32 * 1024)
        ->and(PaperController::maxKb())->toBe($limit);
    $this->get(route('projects.setup.sources', $this->project))->assertOk()->assertSee('data-max-kb="'.$limit.'"', false)->assertSee(UploadLimits::mb($limit));
});
