<?php

namespace App\Http\Controllers;

use App\Enums\DatasetStatus;
use App\Enums\DocumentType;
use App\Enums\ResearchType;
use App\Enums\WorkflowStatus;
use App\Jobs\ValidateDataset;
use App\Models\Dataset;
use App\Models\Project;
use App\Services\Workflow\DocumentStore;
use App\Services\Workflow\ProjectWorkflow;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stage 2: the researcher uploads the data they collected with the delivered instrument(s). Unlocked only once
 * Stage 1 is delivered. Files are checked as they arrive and can be replaced or removed until the researcher
 * confirms the submission, which starts Stage 3.
 */
class DatasetController extends Controller
{
    private const MAX_TRANSCRIPTS = 60;

    public function index(Project $project, DocumentStore $documents): View
    {
        $this->authorize('view', $project);
        $status = $project->workflow_status;

        return view('projects.data', [
            'project' => $project,
            'unlocked' => ProjectWorkflow::dataOpen($project),
            'answering' => $status === WorkflowStatus::NeedsInfo,
            'submitted' => $project->data_submitted_at !== null,
            'kinds' => $this->kinds($project),
            'pending' => $project->pendingDatasets()->get(),
            'submittedFiles' => $project->submittedDatasets()->get(),
            'instruments' => $documents->latestDelivered($project)->where('stage', 1)->where('type', DocumentType::Instrument),
            'limits' => [
                'table' => UploadLimits::mb(UploadLimits::fileKb((int) config('researchhub.uploads.dataset_max_kb'))),
                'transcript' => UploadLimits::mb(UploadLimits::fileKb((int) config('researchhub.uploads.transcript_max_kb'))),
            ],
            'fingerprint' => app(ProjectController::class)->fingerprint($project),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $project);

        if (! ProjectWorkflow::dataOpen($project)) {
            return $this->fail($request, $project, 'Data can be uploaded once your Chapters 1–3 and instrument have been delivered, and until you submit it.');
        }

        $kinds = $this->kinds($project);
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys($kinds))]])['kind'];
        $transcript = $kind === Dataset::KIND_TRANSCRIPT;
        $formats = $transcript ? Dataset::TRANSCRIPT_FORMATS : ($kind === Dataset::KIND_CODES ? ['csv', 'xlsx'] : Dataset::TABULAR_FORMATS);
        $maxKb = UploadLimits::fileKb((int) config($transcript ? 'researchhub.uploads.transcript_max_kb' : 'researchhub.uploads.dataset_max_kb'));

        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb, 'extensions:'.implode(',', $formats)],
        ], [
            'file.extensions' => $transcript
                ? 'Transcripts must be Word (.docx), PDF or plain text (.txt) files.'
                : 'Upload your data as CSV, Excel (.xlsx), SPSS (.sav) or Stata (.dta).',
            'file.max' => 'The file is larger than '.UploadLimits::mb($maxKb).'.',
            'file.uploaded' => 'The file is larger than the server accepts ('.UploadLimits::mb($maxKb).').',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if ($transcript && $project->pendingDatasets()->where('kind', $kind)->count() >= self::MAX_TRANSCRIPTS) {
            return $this->fail($request, $project, 'You can upload at most '.self::MAX_TRANSCRIPTS.' transcripts.');
        }

        // One dataset file per kind: a new upload replaces the earlier one.
        if (! $transcript) {
            $project->pendingDatasets()->where('kind', $kind)->get()->each(fn (Dataset $old) => $this->remove($old));
        }

        $path = Storage::disk(config('researchhub.disk'))->putFileAs("datasets/{$project->id}", $file, Str::uuid().'.'.$extension);

        $dataset = $project->datasets()->create([
            'kind' => $kind,
            'format' => $extension,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'status' => DatasetStatus::Uploaded,
        ]);

        ValidateDataset::dispatch($dataset);

        if ($request->expectsJson()) {
            return response()->json(['dataset' => $this->present($dataset->fresh())], 201);
        }

        return redirect()->route('projects.data', $project)->with('status', $dataset->original_name.' uploaded. It is being checked.');
    }

    /** Polled while files are being checked. */
    public function status(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(['datasets' => $project->pendingDatasets()->get()->map(fn (Dataset $d) => $this->present($d))->all()]);
    }

    public function destroy(Request $request, Dataset $dataset): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $dataset);
        $project = $dataset->project;

        if ($dataset->submitted_at || ! ProjectWorkflow::dataOpen($project)) {
            return $this->fail($request, $project, 'Submitted data can no longer be changed.');
        }

        $this->remove($dataset);

        return $request->expectsJson()
            ? response()->json(['deleted' => true])
            : redirect()->route('projects.data', $project)->with('status', 'File removed.');
    }

    /** The researcher confirms their data; the project returns to the team's queue for Stage 3. */
    public function submit(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $project);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Please confirm that these are the final data files.']);

        if ($project->workflow_status !== WorkflowStatus::Stage1Delivered) {
            return redirect()->route('projects.data', $project)->with('error', 'This data has already been submitted.');
        }

        $pending = $project->pendingDatasets()->get();

        if ($pending->contains(fn (Dataset $d) => in_array($d->status, [DatasetStatus::Uploaded, DatasetStatus::Validating], true))) {
            return back()->with('error', 'Some files are still being checked. Wait a moment, then submit.');
        }
        if ($bad = $pending->first(fn (Dataset $d) => $d->status === DatasetStatus::Invalid)) {
            return back()->with('error', "{$bad->original_name} has problems. Replace or remove it before submitting.");
        }

        foreach ($this->required($project) as $kind => $label) {
            if (! $pending->contains('kind', $kind)) {
                return back()->with('error', "Upload {$label} before submitting.");
            }
        }

        $workflow->submitData($project, $request->user());

        return redirect()->route('projects.show', $project)->with('status', 'Thank you. Your data has been submitted. Our team will now prepare your complete thesis.');
    }

    /** The researcher's own uploaded file. */
    public function file(Dataset $dataset): StreamedResponse
    {
        $this->authorize('view', $dataset);

        return Storage::disk(config('researchhub.disk'))->download($dataset->file_path, $dataset->original_name);
    }

    /** Upload kinds offered for the project's methodology. @return array<string, string> */
    private function kinds(Project $project): array
    {
        return match ($project->research_type) {
            ResearchType::Quantitative => [Dataset::KIND_QUANTITATIVE => 'Survey data'],
            ResearchType::Qualitative => [Dataset::KIND_TRANSCRIPT => 'Interview transcripts', Dataset::KIND_CODES => 'Coded data (optional)'],
            ResearchType::Mixed => [Dataset::KIND_QUANTITATIVE => 'Survey data', Dataset::KIND_TRANSCRIPT => 'Interview transcripts'],
        };
    }

    /** What must be uploaded before submitting. @return array<string, string> */
    private function required(Project $project): array
    {
        return match ($project->research_type) {
            ResearchType::Quantitative => [Dataset::KIND_QUANTITATIVE => 'your survey data file'],
            ResearchType::Qualitative => [Dataset::KIND_TRANSCRIPT => 'at least one interview transcript'],
            ResearchType::Mixed => [Dataset::KIND_QUANTITATIVE => 'your survey data file', Dataset::KIND_TRANSCRIPT => 'at least one interview transcript'],
        };
    }

    private function remove(Dataset $dataset): void
    {
        Storage::disk(config('researchhub.disk'))->delete(array_filter([$dataset->file_path, $dataset->converted_path]));
        $dataset->delete();
    }

    private function present(Dataset $dataset): array
    {
        $report = $dataset->validation_report ?? [];

        return [
            'id' => $dataset->id,
            'kind' => $dataset->kind,
            'name' => $dataset->original_name,
            'size' => $dataset->sizeForHumans(),
            'status' => $dataset->status->value,
            'label' => $dataset->status === DatasetStatus::Accepted ? 'Ready' : $dataset->status->label(),
            'badge' => $dataset->status->badge(),
            'rows' => $dataset->kind === Dataset::KIND_TRANSCRIPT ? null : ($report['rows'] ?? null),
            'columns' => $dataset->kind === Dataset::KIND_TRANSCRIPT ? null : count(array_filter($report['header'] ?? [])),
            'errors' => $report['errors'] ?? [],
            'warnings' => $report['warnings'] ?? [],
            'delete_url' => route('datasets.destroy', $dataset),
        ];
    }

    private function fail(Request $request, Project $project, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : redirect()->route('projects.data', $project)->with('error', $message);
    }
}
