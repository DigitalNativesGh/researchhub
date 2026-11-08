<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAuditLog;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Researcher downloads of the edited documents the team has delivered. */
class DeliverableController extends Controller
{
    public function download(Project $project, ProjectDocument $document): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless((int) $document->project_id === (int) $project->id, 404);
        $this->authorize('download', $document);

        ProjectAuditLog::record($project, 'document.downloaded', "Researcher downloaded {$document->label} v{$document->version}", $document);

        return Storage::disk(config('researchhub.disk'))->download($document->file_path, $document->downloadName(), [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
