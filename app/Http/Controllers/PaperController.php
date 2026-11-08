<?php

namespace App\Http\Controllers;

use App\Enums\PaperStatus;
use App\Models\Paper;
use App\Models\Project;
use App\Models\ProjectAuditLog;
use App\Services\Workflow\ProjectWorkflow;
use App\Support\FileSignature;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The researcher's sources and institutional guidelines or templates. Files are stored privately and handed to
 * the team as they are. They can be changed until the project is submitted, or when the team asks for more.
 */
class PaperController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.sources', [
            'project' => $project,
            'papers' => $project->sources()->get(),
            'guidelineFiles' => $project->guidelineFiles()->get(),
            'editable' => ProjectWorkflow::sourcesOpen($project),
            'maxMb' => UploadLimits::mb(self::maxKb()),
            'maxKb' => self::maxKb(),
            'maxCount' => config('researchhub.uploads.paper_max_count'),
            'minSources' => (int) config('researchhub.submission.min_sources'),
            'formats' => self::acceptAttribute(),
            'fingerprint' => app(ProjectController::class)->fingerprint($project),
        ]);
    }

    /**
     * The upload page sends one file per request (so a large batch never exceeds the server's request limit);
     * a plain form post with several files still works. Script uploads get JSON back.
     */
    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        if (! ProjectWorkflow::sourcesOpen($project)) {
            $message = 'Sources are locked once the project is submitted. Contact us if something needs to change.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : redirect()->route('projects.sources', $project)->with('error', $message);
        }

        $max = self::maxKb();
        $limit = (int) config('researchhub.uploads.paper_max_count');

        $data = $request->validate([
            'kind' => ['nullable', Rule::in([Paper::KIND_SOURCE, Paper::KIND_GUIDELINES])],
            'papers' => ['required', 'array', 'min:1', 'max:'.$limit],
            'papers.*' => ['file', 'max:'.$max],
        ], [
            'papers.required' => 'Choose at least one file to upload.',
            'papers.*.max' => 'Each file must be at most '.UploadLimits::mb($max).'.',
            'papers.*.uploaded' => 'A file was too large for the server (at most '.UploadLimits::mb($max).' per file).',
        ]);

        $kind = $data['kind'] ?? Paper::KIND_SOURCE;
        $disk = Storage::disk(config('researchhub.disk'));
        $existing = $project->papers()->count();
        $rejected = [];
        $added = [];

        foreach ($request->file('papers', []) as $file) {
            /** @var UploadedFile $file */
            $name = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, Paper::FORMATS, true) || ! FileSignature::matches($file->getRealPath(), $extension)) {
                $rejected[] = "{$name} was not uploaded: upload PDF, Word (.docx or .doc), RTF, OpenDocument (.odt) or plain text (.txt) files.";

                continue;
            }

            if ($existing + count($added) >= $limit) {
                $rejected[] = "{$name} was not uploaded: a project can have at most {$limit} files.";

                continue;
            }

            $hash = hash_file('sha256', $file->getRealPath());
            if ($project->papers()->where('sha256', $hash)->exists()) {
                $rejected[] = "{$name} is already in this project.";

                continue;
            }

            $path = $disk->putFileAs("papers/{$project->id}", $file, Str::uuid().'.'.$extension);

            $added[] = $project->papers()->create([
                'kind' => $kind,
                'original_name' => Str::limit($name, 250, ''),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'sha256' => $hash,
                'parse_status' => PaperStatus::Ready,
            ]);
        }

        if ($added && $project->workflow_status->isActive()) {
            ProjectAuditLog::record($project, 'sources.added', 'Researcher added '.count($added).' file(s) after submission', user: $request->user());
        }

        if ($request->expectsJson()) {
            return response()->json(['added' => count($added), 'rejected' => $rejected], $added ? 201 : 422);
        }

        $redirect = redirect($request->input('redirect') === 'setup' ? route('projects.setup.sources', $project) : route('projects.sources', $project));
        if ($added) {
            $redirect->with('status', count($added).' '.Str::plural('file', count($added)).' uploaded.');
        }
        if ($rejected) {
            $redirect->withErrors(['papers' => $rejected]);
        }

        return $redirect;
    }

    /** Per-file size limit the server can actually accept. */
    public static function maxKb(): int
    {
        return UploadLimits::fileKb((int) config('researchhub.uploads.paper_max_kb'));
    }

    /** The file input's accept attribute. */
    public static function acceptAttribute(): string
    {
        return implode(',', array_map(fn ($e) => '.'.$e, Paper::FORMATS));
    }

    public function destroy(Request $request, Paper $paper): RedirectResponse
    {
        $this->authorize('delete', $paper);
        $project = $paper->project;

        if (! ProjectWorkflow::sourcesOpen($project)) {
            return back()->with('error', 'Sources are locked once the project is submitted.');
        }

        Storage::disk(config('researchhub.disk'))->delete($paper->file_path);
        $paper->delete();

        $route = $request->input('redirect') === 'setup' ? 'projects.setup.sources' : 'projects.sources';

        return redirect()->route($route, $project)->with('status', 'File removed.');
    }

    public function file(Paper $paper): StreamedResponse
    {
        $this->authorize('view', $paper);

        return Storage::disk(config('researchhub.disk'))->download($paper->file_path, $paper->original_name);
    }
}
