<?php

use App\Enums\DatasetStatus;
use App\Enums\WorkflowStatus as W;
use App\Models\Dataset;
use App\Notifications\Admin\DataSubmitted;
use App\Notifications\Researcher\DataReceived;
use App\Services\Workflow\ProjectWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function xlsxUpload(string $name = 'responses.xlsx'): UploadedFile
{
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([['ID', 'Q1', 'Q2'], [1, 4, 5], [2, 3, 2], [3, 5, 5]]);
    $path = tempnam(sys_get_temp_dir(), 'rh-xlsx').'.xlsx';
    (new Xlsx($sheet))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

function dataFile(string $ext): UploadedFile
{
    return match ($ext) {
        'csv' => UploadedFile::fake()->createWithContent('responses.csv', responsesCsv()),
        'xlsx' => xlsxUpload(),
        'sav' => UploadedFile::fake()->createWithContent('responses.sav', '$FL2@(#) SPSS DATA FILE'.str_repeat(' ', 200)),
        'dta' => UploadedFile::fake()->createWithContent('responses.dta', '<stata_dta><header><release>118</release></header>'),
    };
}

it('stays locked until stage 1 is delivered', function (string $at) {
    $project = projectAt($at);
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => dataFile('csv')])->assertStatus(422);
    $this->get(route('projects.show', $project))->assertDontSee(route('projects.data', $project));
    expect($project->datasets()->count())->toBe(0);
})->with(['draft', 'submitted', 'stage1_in_progress']);

it('accepts survey data as CSV, Excel, SPSS or Stata and checks it', function (string $ext) {
    $project = projectAt('stage1_delivered');
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => dataFile($ext)])->assertCreated()->assertJsonPath('dataset.name', "responses.{$ext}");

    expect($project->datasets()->sole()->status)->toBe(DatasetStatus::Valid);
})->with(['csv', 'xlsx', 'sav', 'dta']);

it('reports the rows and columns it found in survey data', function () {
    $project = projectAt('stage1_delivered');
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => dataFile('csv')])->assertCreated();
    $this->getJson(route('datasets.status', $project))->assertJsonPath('datasets.0.rows', 40)->assertJsonPath('datasets.0.columns', 5);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => xlsxUpload()])->assertCreated();
    $this->getJson(route('datasets.status', $project))->assertJsonPath('datasets.0.rows', 3)->assertJsonPath('datasets.0.columns', 3);
    expect($project->datasets()->sole()->row_count)->toBe(3);
});

it('rejects wrong types and files whose contents do not match', function () {
    $project = projectAt('stage1_delivered');
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => UploadedFile::fake()->createWithContent('data.json', '{}')])->assertStatus(422)->assertJsonValidationErrors('file');
    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => UploadedFile::fake()->createWithContent('data.sav', 'not spss')])->assertCreated();

    $dataset = $project->datasets()->sole();
    expect($dataset->status)->toBe(DatasetStatus::Invalid)->and($dataset->validation_report['errors'][0])->toContain('SPSS');
});

it('accepts transcripts as Word, PDF or text and rejects empty ones', function () {
    $project = projectAt('stage1_delivered', null, ['research_type' => 'qualitative']);
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'transcript', 'file' => UploadedFile::fake()->createWithContent('P01.txt', transcriptText(1))])->assertCreated();
    $this->postJson(route('datasets.store', $project), ['kind' => 'transcript', 'file' => docxUpload('P02.docx', [transcriptText(2)])])->assertCreated();
    $this->postJson(route('datasets.store', $project), ['kind' => 'transcript', 'file' => pdfUpload('P03.pdf')])->assertCreated();
    $this->postJson(route('datasets.store', $project), ['kind' => 'transcript', 'file' => UploadedFile::fake()->createWithContent('P04.txt', 'Too short.')])->assertCreated();
    $this->postJson(route('datasets.store', $project), ['kind' => 'transcript', 'file' => dataFile('csv')])->assertStatus(422);

    expect($project->datasets()->pluck('status', 'original_name')->map->value->sortKeys()->all())
        ->toBe(['P01.txt' => 'valid', 'P02.docx' => 'valid', 'P03.pdf' => 'valid', 'P04.txt' => 'invalid']);
});

it('replaces the survey file before submitting, and lets the researcher remove files', function () {
    $project = projectAt('stage1_delivered');
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => dataFile('csv')]);
    $first = $project->datasets()->sole();
    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => xlsxUpload()]);

    expect($project->datasets()->sole()->original_name)->toBe('responses.xlsx')
        ->and(Storage::disk(config('researchhub.disk'))->exists($first->file_path))->toBeFalse();

    $this->deleteJson(route('datasets.destroy', $project->datasets()->sole()))->assertOk();
    expect($project->datasets()->count())->toBe(0);
});

it('needs valid files, the required kinds and a confirmation to submit', function () {
    Notification::fake();
    $admin = admin();
    $project = projectAt('stage1_delivered', null, ['research_type' => 'mixed']);
    $this->actingAs($project->user);

    $this->post(route('data.submit', $project), ['confirm' => '1'])->assertSessionHas('error', 'Upload your survey data file before submitting.');

    uploadData($project, Dataset::KIND_QUANTITATIVE, 'responses.csv', responsesCsv());
    $this->post(route('data.submit', $project), ['confirm' => '1'])->assertSessionHas('error', 'Upload at least one interview transcript before submitting.');

    $bad = uploadData($project, Dataset::KIND_TRANSCRIPT, 'P01.txt', 'empty');
    $this->post(route('data.submit', $project), ['confirm' => '1'])->assertSessionHas('error');
    $bad->delete();

    uploadData($project, Dataset::KIND_TRANSCRIPT, 'P02.txt', transcriptText(2));
    $this->post(route('data.submit', $project), [])->assertSessionHasErrors('confirm');
    $this->post(route('data.submit', $project), ['confirm' => '1'])->assertRedirect(route('projects.show', $project));

    expect($project)->toBeAt(W::DataSubmitted)->and($project->submittedDatasets()->count())->toBe(2);
    Notification::assertSentTo($project->user, DataReceived::class);
    Notification::assertSentTo($admin, DataSubmitted::class);
});

it('locks submitted data', function () {
    $project = projectAt('data_submitted');
    $this->actingAs($project->user);

    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => dataFile('csv')])->assertStatus(422);
    $this->deleteJson(route('datasets.destroy', $project->datasets()->first()))->assertStatus(422);
    $this->post(route('data.submit', $project), ['confirm' => '1'])->assertSessionHas('error');
    expect($project->datasets()->count())->toBe(1);
});

it('lets the researcher add data when the team asks during stage 3, and the new files join the submission', function () {
    $project = projectAt('data_submitted');
    app(ProjectWorkflow::class)->requestInfo($project, admin(), 'The CSV is missing the age column.');
    $this->actingAs($project->user);

    $this->get(route('projects.data', $project))->assertOk();
    $this->postJson(route('datasets.store', $project), ['kind' => 'quantitative', 'file' => xlsxUpload('responses_fixed.xlsx')])->assertCreated();
    $this->post(route('projects.respond', $project), ['reply' => 'Uploaded a corrected file.']);

    expect($project)->toBeAt(W::DataSubmitted)
        ->and($project->submittedDatasets()->pluck('original_name')->all())->toContain('responses_fixed.xlsx');
});
