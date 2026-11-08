<?php

use App\Enums\DocumentType;
use App\Enums\WorkflowStatus;
use App\Jobs\ValidateDataset;
use App\Models\Dataset;
use App\Models\Project;
use App\Models\User;
use App\Services\Claude\ClaudeGateway;
use App\Services\Claude\ClaudeService;
use App\Services\Claude\FakeGateway;
use App\Services\Workflow\DocumentStore;
use App\Services\Workflow\ProjectWorkflow;
use App\Support\SamplePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Tests\TestCase;

/*
| Every test runs against an in-memory SQLite database, the sync queue and the fake Claude gateway (see
| phpunit.xml). Nothing reaches the network.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Unit');

/** Swap in a fresh fake Claude gateway and return it so tests can queue responses and make assertions. */
function fakeClaude(): FakeGateway
{
    $fake = new FakeGateway;
    app()->instance(ClaudeGateway::class, $fake);
    app()->forgetInstance(ClaudeService::class);

    return $fake;
}

/** A PDF upload. */
function pdfUpload(string $name = 'Owusu_2021_mobile-learning.pdf', int $pages = 2): UploadedFile
{
    $body = array_fill(0, $pages, 'Sample page text about mobile learning adoption among university students.');

    return UploadedFile::fake()->createWithContent($name, SamplePdf::make(pathinfo($name, PATHINFO_FILENAME), $body));
}

/** Store sample source PDFs the way PaperController does. */
function addPapers(Project $project, array $names = ['Owusu_2021_adoption.pdf', 'Nkrumah_2022_performance.pdf', 'Bello_2023_connectivity.pdf'], string $kind = 'source'): void
{
    foreach ($names as $name) {
        $pdf = SamplePdf::make($name, ['Sample text '.$name]);
        $path = "papers/{$project->id}/".Str::uuid().'.pdf';
        Storage::disk(config('researchhub.disk'))->put($path, $pdf);
        $project->papers()->create([
            'kind' => $kind, 'original_name' => $name, 'file_path' => $path, 'file_size' => strlen($pdf),
            'sha256' => hash('sha256', $pdf.$name), 'parse_status' => 'ready',
        ]);
    }
}

/** Survey responses as a researcher might export them. */
function responsesCsv(int $rows = 40): string
{
    mt_srand(7);
    $lines = ['ID,Q1,Q2,Q3,AGE'];
    for ($r = 1; $r <= $rows; $r++) {
        $lines[] = implode(',', [$r, mt_rand(1, 5), mt_rand(1, 5), mt_rand(1, 5), mt_rand(18, 30)]);
    }

    return implode("\n", $lines)."\n";
}

function transcriptText(int $n): string
{
    return "Interviewer: How do you use your phone for study?\nParticipant {$n}: The cost of mobile data makes it very hard for me to download lecture videos every week. "
        .'I usually wait until I am on campus where the Wi-Fi is free. My lecturers sometimes reply to questions on the class group in the evening.';
}

/** A confirmed Paystack payment the user has not used for a project yet. */
function paidFor(User $user, string $level = 'mphil', int $times = 1): void
{
    foreach (range(1, $times) as $i) {
        $user->payments()->create([
            'degree_level' => $level, 'amount' => 80000, 'currency' => 'GHS', 'reference' => 'TEST-'.Str::random(12),
            'status' => 'success', 'channel' => 'card', 'card_type' => 'visa', 'last4' => '4081', 'paid_at' => now(),
        ]);
    }
}

/** An administrator account. */
function admin(): User
{
    $admin = User::factory()->create(['name' => 'Ada Admin']);
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

/** A real Word file. Lines starting with "# " become headings. */
function docxUpload(string $name, array $lines = ['Document written by the team.']): UploadedFile
{
    Settings::setOutputEscapingEnabled(true);
    $word = new PhpWord;
    $word->addTitleStyle(1, ['bold' => true, 'size' => 16]);
    $section = $word->addSection();
    foreach ($lines as $line) {
        preg_match('/^#\s+(.+)$/', $line, $m) ? $section->addTitle($m[1], 1) : $section->addText($line);
    }
    $path = tempnam(sys_get_temp_dir(), 'rh-test').'.docx';
    IOFactory::createWriter($word, 'Word2007')->save($path);

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

/** Store a researcher data file the way DatasetController does and check it. */
function uploadData(Project $project, string $kind, string $name, string $contents): Dataset
{
    $path = "datasets/{$project->id}/".Str::uuid().'.'.pathinfo($name, PATHINFO_EXTENSION);
    Storage::disk(config('researchhub.disk'))->put($path, $contents);
    $dataset = $project->datasets()->create([
        'kind' => $kind, 'format' => pathinfo($name, PATHINFO_EXTENSION), 'original_name' => $name,
        'file_path' => $path, 'file_size' => strlen($contents), 'status' => 'uploaded',
    ]);
    ValidateDataset::dispatchSync($dataset);

    return $dataset->fresh();
}

/** The team uploads Chapters 1–3 and the instrument(s) and delivers them. */
function deliverStage1(Project $project, ?User $admin = null): void
{
    $admin ??= admin();
    $store = app(DocumentStore::class);
    $store->storeDeliverable($project, DocumentType::Chapters, 'Chapters 1–3', docxUpload('chapters.docx', ['# Chapter 1', 'Written by the team.']), $admin);
    $store->storeDeliverable($project, DocumentType::Instrument, 'Questionnaire', docxUpload('questionnaire.docx', ['# Questionnaire', 'Q1. Item one.']), $admin, 'questionnaire');
    app(ProjectWorkflow::class)->deliver($project->fresh(), 1, $admin);
}

/**
 * Walk a project through the service until it reaches $stop: draft, submitted, stage1_in_progress,
 * stage1_delivered, data_submitted, stage3_in_progress, completed, needs_info or on_hold.
 */
function projectAt(string $stop, ?User $user = null, array $attributes = []): Project
{
    $workflow = app(ProjectWorkflow::class);
    $project = Project::factory()->for($user ?? User::factory()->create())->create($attributes);
    addPapers($project);
    $admin = User::where('is_admin', true)->first() ?? admin();

    if ($stop === 'draft') {
        return $project->fresh();
    }

    $workflow->submit($project, $project->user);
    if ($stop === 'submitted') {
        return $project->fresh();
    }
    if (in_array($stop, ['needs_info', 'on_hold'], true)) {
        $stop === 'needs_info'
            ? $workflow->requestInfo($project->fresh(), $admin, 'Please upload your department template.')
            : $workflow->hold($project->fresh(), $admin, 'Waiting for payment confirmation');

        return $project->fresh();
    }

    $workflow->markInProgress($project->fresh(), $admin, 'Esi Writer', now()->addWeek()->toDateString(), 'Internal note');
    if ($stop === 'stage1_in_progress') {
        return $project->fresh();
    }

    deliverStage1($project->fresh(), $admin);
    if ($stop === 'stage1_delivered') {
        return $project->fresh();
    }

    $project = $project->fresh();
    if ($project->needsQuantitativeData()) {
        uploadData($project, Dataset::KIND_QUANTITATIVE, 'responses.csv', responsesCsv());
    }
    if ($project->needsQualitativeData()) {
        uploadData($project, Dataset::KIND_TRANSCRIPT, 'P01.txt', transcriptText(1));
    }
    $workflow->submitData($project->fresh(), $project->user);
    if ($stop === 'data_submitted') {
        return $project->fresh();
    }

    $workflow->markInProgress($project->fresh(), $admin, 'Kofi Writer', null, null);
    if ($stop === 'stage3_in_progress') {
        return $project->fresh();
    }

    app(DocumentStore::class)->storeDeliverable($project, DocumentType::Thesis, 'Complete thesis', docxUpload('thesis.docx', ['# Thesis', 'The complete thesis.']), $admin);
    $workflow->deliver($project->fresh(), 3, $admin);

    return $project->fresh();
}

expect()->extend('toBeAt', function (WorkflowStatus $status) {
    $project = $this->value->fresh();

    return expect($project->workflow_status)->toBe($status, "Project is at {$project->workflow_status->value}");
});
