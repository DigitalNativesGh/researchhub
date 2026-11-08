<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentType;
use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\Paper;
use App\Models\Project;
use App\Models\ProjectAuditLog;
use App\Models\ProjectDocument;
use App\Services\Workflow\DeliveryNotReady;
use App\Services\Workflow\DocumentStore;
use App\Services\Workflow\InvalidTransition;
use App\Services\Workflow\ProjectWorkflow;
use App\Support\FileSignature;
use App\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What an administrator does with a project: download the researcher's sources and data (one by one or as a
 * ZIP), mark the stage as in progress, upload the documents the team wrote and deliver them, ask the researcher
 * for information, and pause or resume the project. Every download, upload and change is audited.
 */
class WorkflowController extends Controller
{
    /* ---------------------------------------------------------------- Downloads */

    public function paper(Project $project, Paper $paper): StreamedResponse
    {
        abort_unless((int) $paper->project_id === (int) $project->id, 404);
        ProjectAuditLog::record($project, 'source.downloaded', "Admin downloaded {$paper->original_name}");

        return Storage::disk(config('researchhub.disk'))->download($paper->file_path, $paper->original_name);
    }

    /** Every source paper and guideline file in one ZIP named with the project reference. */
    public function sources(Project $project): BinaryFileResponse
    {
        $papers = $project->papers()->get();
        abort_if($papers->isEmpty(), 404, 'This project has no sources.');
        ProjectAuditLog::record($project, 'sources.downloaded', 'Admin downloaded all sources as a ZIP ('.$papers->count().' files)');

        return $this->zip($project->reference().'_sources.zip', $papers->map(fn (Paper $p) => [
            'path' => $p->file_path,
            'name' => ($p->isGuidelines() ? 'Guidelines/' : '').$p->original_name,
        ]));
    }

    public function dataset(Project $project, Dataset $dataset): StreamedResponse
    {
        abort_unless((int) $dataset->project_id === (int) $project->id, 404);
        ProjectAuditLog::record($project, 'data.downloaded', "Admin downloaded data file {$dataset->original_name}");

        return Storage::disk(config('researchhub.disk'))->download($dataset->file_path, $dataset->original_name);
    }

    /** The researcher's submitted data in one ZIP named with the project reference. */
    public function data(Project $project): BinaryFileResponse
    {
        $datasets = $project->submittedDatasets()->get();
        abort_if($datasets->isEmpty(), 404, 'The researcher has not submitted any data yet.');
        ProjectAuditLog::record($project, 'data.downloaded', 'Admin downloaded all data as a ZIP ('.$datasets->count().' files)');

        return $this->zip($project->reference().'_data.zip', $datasets->map(fn (Dataset $d) => ['path' => $d->file_path, 'name' => $d->original_name]));
    }

    public function download(Project $project, ProjectDocument $document): StreamedResponse
    {
        abort_unless((int) $document->project_id === (int) $project->id && $document->kind === ProjectDocument::DELIVERABLE, 404);
        ProjectAuditLog::record($project, 'document.downloaded', "Admin downloaded {$document->label} v{$document->version}", $document);

        return Storage::disk(config('researchhub.disk'))->download($document->file_path, $document->adminDownloadName(), ['Cache-Control' => 'private, no-store']);
    }

    /* ---------------------------------------------------------------- The team's work */

    public function markInProgress(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'writer_name' => ['nullable', 'string', 'max:120'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(fn () => $workflow->markInProgress($project, $request->user(), $data['writer_name'] ?? null, $data['expected_at'] ?? null, $data['notes'] ?? null), 'Marked as in progress.');
    }

    public function upload(Request $request, Project $project, DocumentStore $store): RedirectResponse
    {
        $maxKb = UploadLimits::fileKb((int) config('researchhub.uploads.deliverable_max_kb'));
        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            'instrument' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:'.$maxKb],
        ], [
            'file.max' => 'Files can be at most '.UploadLimits::mb($maxKb).'.',
            'file.uploaded' => 'The file is larger than the server accepts ('.UploadLimits::mb($maxKb).').',
        ]);

        $type = DocumentType::from($data['type']);
        $file = $request->file('file');
        $format = strtolower($file->getClientOriginalExtension());
        $allowed = DocumentStore::FORMATS[$type->value];

        if (! in_array($format, $allowed, true) || ! FileSignature::matches($file->getRealPath(), $format)) {
            return back()->withErrors(['file' => 'Upload the '.strtolower($type->label()).' as '.implode(', ', array_map('strtoupper', $allowed)).'.'])->withInput();
        }

        if ($project->workflow_status === WorkflowStatus::Draft) {
            return back()->with('error', 'The researcher has not submitted this project yet.');
        }
        if ($type->stage() === 3 && $project->submittedDatasets()->doesntExist() && ! $project->documents()->deliverables()->where('stage', 3)->exists()) {
            return back()->with('error', 'The complete thesis can be uploaded once the researcher has submitted their data.');
        }

        $label = $type === DocumentType::Instrument ? (trim((string) ($data['instrument'] ?? '')) ?: 'Data collection instrument') : $type->label();
        $document = $store->storeDeliverable($project, $type, $label, $file, $request->user(), $type === DocumentType::Instrument ? Str::slug($label, '_') : null, $data['notes'] ?? null);

        return back()->with('status', "Uploaded {$document->label} v{$document->version}. It reaches the researcher when you deliver.");
    }

    public function deliver(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $stage = (int) $request->validate(['stage' => ['required', Rule::in([1, 3])], 'confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that the documents are ready for the researcher.',
        ])['stage'];

        return $this->attempt(fn () => $workflow->deliver($project, $stage, $request->user()),
            $stage === 1 ? 'Delivered. The researcher has been asked to collect and upload their data.' : 'Final thesis delivered to the researcher.');
    }

    /* ---------------------------------------------------------------- Needs information / on hold */

    public function requestInfo(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $message = $request->validate(['message' => ['required', 'string', 'max:2000']], ['message.required' => 'Write what you need from the researcher.'])['message'];

        return $this->attempt(fn () => $workflow->requestInfo($project, $request->user(), $message), 'The researcher has been asked for information.');
    }

    public function hold(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        return $this->attempt(fn () => $workflow->hold($project, $request->user(), $reason), 'Project put on hold.');
    }

    public function resume(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        return $this->attempt(fn () => $workflow->resume($project, $request->user()), 'Project resumed.');
    }

    /* ---------------------------------------------------------------- Helpers */

    /** @param Collection<int, array{path: string, name: string}> $files */
    private function zip(string $name, Collection $files): BinaryFileResponse
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'rh-zip').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $disk = Storage::disk(config('researchhub.disk'));
        $used = [];

        foreach ($files as $file) {
            $entry = preg_replace('/[^\w.\-\/ ]+/u', '_', $file['name']);
            $base = $entry;
            for ($n = 2; in_array($entry, $used, true); $n++) {
                $entry = preg_replace('/(\.[^.\/]+)?$/', " ({$n})$1", $base, 1);
            }
            $used[] = $entry;
            $zip->addFromString($entry, (string) $disk->get($file['path']));
        }
        $zip->close();

        return response()->download($zipPath, $name)->deleteFileAfterSend();
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidTransition|DeliveryNotReady $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $success);
    }
}
