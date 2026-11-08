<?php

namespace App\Http\Controllers;

use App\Enums\WorkflowStatus;
use App\Models\Project;
use App\Services\Workflow\ProjectWorkflow;
use App\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Wizard steps 4–5: upload sources (and institutional guidelines), review, and submit. Submitting sends the
 * project to the team's work queue and locks the sources unless the team asks for more information.
 */
class ProjectSetupController extends Controller
{
    public function sources(Project $project): View|RedirectResponse
    {
        $this->authorize('update', $project);

        if ($project->workflow_status !== WorkflowStatus::Draft) {
            return redirect()->route('projects.sources', $project);
        }

        return view('projects.setup.sources', [
            'project' => $project->load('papers'),
            'maxMb' => UploadLimits::mb(PaperController::maxKb()),
            'maxKb' => PaperController::maxKb(),
            'maxCount' => config('researchhub.uploads.paper_max_count'),
            'minSources' => (int) config('researchhub.submission.min_sources'),
            'formats' => PaperController::acceptAttribute(),
        ]);
    }

    public function review(Project $project): View|RedirectResponse
    {
        $this->authorize('update', $project);

        if ($project->workflow_status !== WorkflowStatus::Draft) {
            return redirect()->route('projects.show', $project);
        }

        return view('projects.setup.review', [
            'project' => $project->load('papers'),
            'minSources' => (int) config('researchhub.submission.min_sources'),
        ]);
    }

    /** Confirmation step: the researcher submits their project. */
    public function submit(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $project);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Please confirm that your title, details and sources are correct.']);

        if ($project->workflow_status !== WorkflowStatus::Draft) {
            return redirect()->route('projects.show', $project);
        }

        $min = (int) config('researchhub.submission.min_sources');
        if ($project->sources()->count() < $min) {
            return redirect()->route('projects.setup.sources', $project)->with('error', "Upload at least {$min} source ".str('paper')->plural($min).' before submitting.');
        }

        $workflow->submit($project, $request->user());

        return redirect()->route('projects.show', $project)->with('status', 'Your project has been submitted. We will let you know when your Chapters 1–3 and instrument are ready.');
    }

    /** The researcher answers a request for information (after updating their sources or data if asked). */
    public function respond(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:3000']]);

        if ($project->workflow_status !== WorkflowStatus::NeedsInfo) {
            return redirect()->route('projects.show', $project);
        }

        if ($project->pendingDatasets()->whereIn('status', ['uploaded', 'validating', 'invalid'])->exists()) {
            return back()->with('error', 'Some data files are still being checked or have problems. Fix or remove them before sending your reply.');
        }

        $workflow->provideInfo($project, $request->user(), $data['reply'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', 'Thank you. Our team has your update.');
    }
}
