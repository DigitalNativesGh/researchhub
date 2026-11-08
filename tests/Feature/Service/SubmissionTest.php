<?php

use App\Enums\WorkflowStatus as W;
use App\Models\Paper;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Admin\ProjectSubmitted;
use App\Notifications\Researcher\ProjectReceived;
use App\Services\Workflow\ProjectWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->actingAs($this->project->user);
});

function fileOfType(string $ext): UploadedFile
{
    return match ($ext) {
        'pdf' => pdfUpload('Owusu_2021_paper.pdf'),
        'docx' => docxUpload('Mensah_2020_chapter.docx', ['A source written in Word.']),
        'doc' => UploadedFile::fake()->createWithContent('Report_2019.doc', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 504).'Word 97 body'),
        'rtf' => UploadedFile::fake()->createWithContent('Notes_2018.rtf', '{\rtf1\ansi Rich text source}'),
        'odt' => (function () {
            $path = tempnam(sys_get_temp_dir(), 'odt');
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
            $zip->addFromString('content.xml', '<office:document-content/>');
            $zip->close();

            return new UploadedFile($path, 'Paper_2022.odt', null, null, true);
        })(),
        'txt' => UploadedFile::fake()->createWithContent('Abstracts.txt', 'Plain text notes about the sources.'),
    };
}

it('accepts sources in every supported format', function (string $ext) {
    $this->postJson(route('papers.store', $this->project), ['papers' => [fileOfType($ext)]])->assertCreated()->assertJson(['added' => 1]);

    expect($this->project->sources()->sole()->extension())->toBe($ext);
})->with(['pdf', 'docx', 'doc', 'rtf', 'odt', 'txt']);

it('rejects files whose contents do not match their extension, and unsupported types', function () {
    $this->postJson(route('papers.store', $this->project), ['papers' => [
        UploadedFile::fake()->createWithContent('fake.pdf', 'MZ not really a pdf'),
        UploadedFile::fake()->createWithContent('renamed.docx', 'plain text pretending'),
        UploadedFile::fake()->createWithContent('data.xlsx', 'PK spreadsheet'),
        UploadedFile::fake()->createWithContent('virus.exe', 'MZ'),
    ]])->assertStatus(422)->assertJsonCount(4, 'rejected');

    expect($this->project->papers()->count())->toBe(0);
});

it('validates size and skips duplicates', function () {
    $this->postJson(route('papers.store', $this->project), ['papers' => [UploadedFile::fake()->create('huge.pdf', 40 * 1024, 'application/pdf')]])->assertStatus(422)->assertJsonValidationErrors('papers.0');

    $pdf = pdfUpload('Same_2020.pdf');
    $this->postJson(route('papers.store', $this->project), ['papers' => [$pdf]])->assertCreated();
    $this->postJson(route('papers.store', $this->project), ['papers' => [pdfUpload('Same_2020.pdf')]])->assertStatus(422)->assertJsonPath('rejected.0', 'Same_2020.pdf is already in this project.');
});

it('keeps institutional guidelines and templates separately from sources', function () {
    $this->postJson(route('papers.store', $this->project), ['kind' => 'guidelines', 'papers' => [docxUpload('Thesis_template.docx', ['Department template'])]])->assertCreated();
    $this->postJson(route('papers.store', $this->project), ['papers' => [pdfUpload()]])->assertCreated();

    expect($this->project->guidelineFiles()->sole()->original_name)->toBe('Thesis_template.docx')
        ->and($this->project->sources()->count())->toBe(1);
    $this->get(route('projects.setup.sources', $this->project))->assertSee('Institutional guidelines or templates')->assertSee('Thesis_template.docx');
});

it('lets the researcher remove files before submitting', function () {
    addPapers($this->project);
    $paper = $this->project->papers()->first();

    $this->delete(route('papers.destroy', $paper))->assertRedirect();

    expect(Paper::find($paper->id))->toBeNull()
        ->and(Storage::disk(config('researchhub.disk'))->exists($paper->file_path))->toBeFalse();
});

it('saves the submission details', function () {
    paidFor($this->project->user, 'phd');
    $this->post(route('projects.new.store'), ['degree_level' => 'phd']);

    $this->post(route('projects.store'), [
        'title' => 'Teacher workload and retention in rural schools', 'discipline' => 'Education', 'degree_level' => 'phd',
        'research_type' => 'mixed', 'citation_style' => 'harvard', 'institution' => 'University of Cape Coast', 'word_count_target' => 80000,
        'guidelines' => 'Five chapters; Harvard referencing; 1.5 line spacing.', 'writer_notes' => 'My supervisor prefers a separate conceptual framework chapter.',
        'deadline' => now()->addMonths(6)->toDateString(), 'description' => 'Objectives here.',
    ])->assertRedirect();

    expect(Project::latest('id')->first())
        ->institution->toBe('University of Cape Coast')->word_count_target->toBe(80000)
        ->guidelines->toContain('Harvard')->writer_notes->toContain('conceptual framework')->title_source->toBe('own');
});

it('needs a confirmation and enough sources to submit, then notifies and locks the sources', function () {
    Notification::fake();
    $admin = admin();
    config(['researchhub.submission.min_sources' => 2]);
    addPapers($this->project, ['One_2020.pdf']);

    $this->post(route('projects.submit', $this->project), ['confirm' => '1'])->assertRedirect(route('projects.setup.sources', $this->project));
    expect($this->project)->toBeAt(W::Draft);

    addPapers($this->project, ['Two_2021.pdf']);
    $this->get(route('projects.setup.review', $this->project))->assertOk()->assertSee('My title, details and sources are correct');
    $this->post(route('projects.submit', $this->project), [])->assertSessionHasErrors('confirm');
    $this->post(route('projects.submit', $this->project), ['confirm' => '1'])->assertRedirect(route('projects.show', $this->project));

    expect($this->project)->toBeAt(W::Submitted)->and($this->project->fresh()->submitted_at)->not->toBeNull();
    Notification::assertSentTo($this->project->user, ProjectReceived::class);
    Notification::assertSentTo($admin, ProjectSubmitted::class);

    // Locked: no adding, removing or editing details.
    $this->postJson(route('papers.store', $this->project), ['papers' => [pdfUpload('Late_2024.pdf')]])->assertStatus(422);
    $this->delete(route('papers.destroy', $this->project->papers()->first()))->assertSessionHas('error');
    $this->get(route('projects.edit', $this->project))->assertRedirect(route('projects.show', $this->project));
    expect($this->project->papers()->count())->toBe(2);
});

it('reopens sources when the team asks for more information, and resumes on reply', function () {
    $project = projectAt('submitted', $this->project->user);
    app(ProjectWorkflow::class)->requestInfo($project, User::where('is_admin', true)->first(), 'Please add your department template.');

    $this->get(route('projects.show', $project))->assertSee('Please add your department template.')->assertSee('Send reply');
    $this->postJson(route('papers.store', $project), ['kind' => 'guidelines', 'papers' => [docxUpload('Template.docx')]])->assertCreated();

    $this->post(route('projects.respond', $project), ['reply' => 'Template uploaded.'])->assertRedirect(route('projects.show', $project));

    expect($project)->toBeAt(W::Submitted)->and($project->fresh()->info_request)->toBeNull();
    $this->postJson(route('papers.store', $project), ['papers' => [pdfUpload('Late_2024.pdf')]])->assertStatus(422);
});
