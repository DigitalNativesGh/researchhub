<?php

namespace App\Http\Controllers;

use App\Enums\CitationStyle;
use App\Enums\DegreeLevel;
use App\Enums\ResearchType;
use App\Enums\WorkflowStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Services\Workflow\DocumentStore;
use App\Services\Workflow\ProjectTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public const DISCIPLINES = [
        'Social Sciences', 'Computer Science & IT', 'Engineering', 'Medicine & Healthcare', 'Business & Economics',
        'Humanities', 'Education', 'Natural Sciences', 'Law', 'Agriculture & Environment', 'Other',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = in_array($request->query('status'), ['progress', 'review', 'completed', 'draft'], true) ? $request->query('status') : 'all';
        $sort = in_array($request->query('sort'), ['recent', 'title', 'deadline'], true) ? $request->query('sort') : 'recent';
        $q = trim((string) $request->query('q'));

        $like = '%'.mb_strtolower($q).'%';
        $base = $user->projects()->when($q !== '', fn ($query) => $query->where(
            fn ($w) => $w->whereRaw('lower(title) like ?', [$like])->orWhereRaw('lower(discipline) like ?', [$like])
        ));

        $counts = (clone $base)->pluck('workflow_status')->countBy(fn (WorkflowStatus $s) => $s->group());
        $filters = [
            'all' => ['All Projects', $counts->sum()],
            'progress' => ['In Progress', $counts->get('progress', 0)],
            'review' => ['Needs Your Data', $counts->get('review', 0)],
            'completed' => ['Completed', $counts->get('completed', 0)],
            'draft' => ['Drafts', $counts->get('draft', 0)],
        ];

        $query = (clone $base)->withCount('papers');
        if ($status !== 'all') {
            $query->whereIn('workflow_status', array_map(fn ($s) => $s->value, WorkflowStatus::forGroup($status)));
        }
        match ($sort) {
            'title' => $query->orderBy('title'),
            'deadline' => $query->orderByRaw('deadline is null')->orderBy('deadline'),
            default => $query->latest('updated_at'),
        };

        $sorts = ['recent' => 'Recent', 'title' => 'Title', 'deadline' => 'Deadline'];
        $keys = array_keys($sorts);

        return view('projects.index', [
            'projects' => $query->paginate(8)->withQueryString(),
            'filters' => $filters,
            'status' => $status,
            'sort' => $sort,
            'sortLabels' => $sorts,
            'nextSort' => $keys[(array_search($sort, $keys, true) + 1) % count($keys)],
            'q' => $q,
            'total' => $user->projects()->count(),
        ]);
    }

    /** Wizard step 1: the student picks the kind of research (degree level) before anything else. */
    public function chooseLevel(Request $request): View
    {
        return view('projects.level', [
            'selected' => old('degree_level', $request->session()->get('wizard.degree_level')),
            'prices' => collect(DegreeLevel::cases())->mapWithKeys(fn ($l) => [$l->value => Payment::priceFor($l)]),
            'credits' => $request->user()->payments()->unused()->pluck('degree_level')->map->value->unique()->values()->all(),
        ]);
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['degree_level' => ['required', Rule::enum(DegreeLevel::class)]],
            ['degree_level.required' => 'Select the type of research you are working on.'],
        );

        $request->session()->put('wizard.degree_level', $data['degree_level']);

        // Step 2 is payment, unless this type is already paid for (or free).
        return $request->user()->canStartProject(DegreeLevel::from($data['degree_level']))
            ? redirect()->route('projects.create')
            : redirect()->route('projects.payment');
    }

    /** Wizard step 3: title and project details. Requires the research type (step 1) to be paid for (step 2). */
    public function create(Request $request): View|RedirectResponse
    {
        $wizard = $request->session()->get('wizard', []);
        $level = DegreeLevel::tryFrom((string) old('degree_level', $wizard['degree_level'] ?? ''));

        if (! $level) {
            return redirect()->route('projects.new');
        }
        if (! $request->user()->canStartProject($level)) {
            return redirect()->route('projects.payment');
        }

        return view('projects.create', [
            'disciplines' => self::DISCIPLINES,
            'values' => [
                'degree_level' => old('degree_level', $wizard['degree_level']),
                'title' => old('title', $wizard['title'] ?? ''),
                'discipline' => old('discipline', $this->matchDiscipline($wizard['discipline'] ?? null)),
                'word_count_target' => old('word_count_target', $wizard['word_count_target'] ?? ''),
                'research_type' => old('research_type', $wizard['research_type'] ?? ResearchType::Quantitative->value),
                'citation_style' => old('citation_style', CitationStyle::Apa7->value),
                'description' => old('description', $wizard['description'] ?? ''),
                'deadline' => old('deadline', ''),
                'institution' => old('institution', $wizard['institution'] ?? $request->user()->institution),
                'guidelines' => old('guidelines', $wizard['guidelines'] ?? ''),
                'writer_notes' => old('writer_notes', $wizard['writer_notes'] ?? ''),
                'title_mode' => old('title_mode', ($wizard['suggested_title'] ?? null) ? 'suggested' : 'own'),
            ],
            'suggestedTitle' => $wizard['suggested_title'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['title_source'] = $this->titleSource($data['title'], $request->session()->get('wizard.suggested_title'));
        $user = $request->user();
        $level = DegreeLevel::from($data['degree_level']);

        $project = DB::transaction(function () use ($user, $level, $data) {
            $credit = $user->payments()->unused()->where('degree_level', $level->value)->oldest('id')->lockForUpdate()->first();

            if (! $credit && Payment::priceFor($level) > 0) {
                return null;
            }

            $project = $user->projects()->create($data + ['status' => 'draft', 'phase' => 1]);
            $credit?->forceFill(['project_id' => $project->id, 'consumed_at' => now()])->save();

            return $project;
        });

        if (! $project) {
            $request->session()->put('wizard.degree_level', $level->value);

            return redirect()->route('projects.payment')->with('error', "Pay for the {$level->label()} before creating the project.");
        }

        $project->record('created', 'Project created', null, 'note_add');
        $request->session()->forget(['wizard', 'title_assistant']);

        return $request->input('intent') === 'draft'
            ? redirect()->route('projects.index')->with('status', 'Draft saved. Continue whenever you are ready.')
            : redirect()->route('projects.setup.sources', $project);
    }

    public function edit(Project $project): View|RedirectResponse
    {
        $this->authorize('update', $project);

        if ($project->workflow_status !== WorkflowStatus::Draft) {
            return redirect()->route('projects.show', $project)->with('error', 'Project details are fixed once the project has started. Contact us if something needs to change.');
        }

        return view('projects.create', [
            'project' => $project,
            'disciplines' => self::DISCIPLINES,
            'values' => [
                'degree_level' => old('degree_level', $project->degree_level->value),
                'title' => old('title', $project->title),
                'discipline' => old('discipline', $project->discipline),
                'word_count_target' => old('word_count_target', $project->word_count_target),
                'research_type' => old('research_type', $project->research_type->value),
                'citation_style' => old('citation_style', $project->citation_style->value),
                'description' => old('description', $project->description),
                'deadline' => old('deadline', $project->deadline?->toDateString()),
                'institution' => old('institution', $project->institution),
                'guidelines' => old('guidelines', $project->guidelines),
                'writer_notes' => old('writer_notes', $project->writer_notes),
                'title_mode' => 'own',
            ],
            'suggestedTitle' => null,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        // Once Stage 1 has started, the documents are being prepared from these details.
        if ($project->workflow_status !== WorkflowStatus::Draft) {
            return redirect()->route('projects.show', $project)->with('error', 'Project details are fixed once the project has started. Contact us if something needs to change.');
        }

        $data = $this->validated($request, $project);

        // The research type is what the student paid for, so it cannot change after the project is created.
        if ($data['degree_level'] !== $project->degree_level->value) {
            return back()->withInput()->withErrors(['degree_level' => 'The research type is fixed once the project is created. Start a new project for a different type.']);
        }

        if ($data['title'] !== $project->title && $project->title_source === 'suggested') {
            $data['title_source'] = 'suggested_edited';
        }

        $project->update($data);
        $project->record('updated', 'Project details updated', null, 'edit');

        return redirect()->route('projects.show', $project)->with('status', 'Project details saved.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        // Not while our team is working on it.
        if (! in_array($project->workflow_status, [WorkflowStatus::Draft, WorkflowStatus::Completed], true)) {
            return redirect()->route('projects.show', $project)->with('error', 'A project can be deleted before it starts or once it is completed. Contact us if you need to stop it.');
        }

        $project->purgeFiles();
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }

    /** Overview: the three-stage progress tracker and the delivered documents. */
    public function show(Project $project, ProjectTimeline $timeline, DocumentStore $documents): View
    {
        $this->authorize('view', $project);

        return view('projects.show', [
            'project' => $project->loadCount('papers'),
            'stages' => $timeline->stages($project),
            'nextStep' => $timeline->nextStep($project),
            'deliverables' => $documents->latestDelivered($project),
            'fingerprint' => $this->fingerprint($project),
        ]);
    }

    /**
     * Polled by project pages to refresh when something changes. Returns only the researcher-facing status:
     * never draft text, pipeline steps or error details.
     */
    public function progress(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $status = $project->workflow_status;

        return response()->json([
            'status' => $status->value,
            'label' => $status->researcherLabel(),
            'message' => $status->researcherMessage(),
            'needs_user' => $status->needsResearcher() || $status === WorkflowStatus::Completed,
            'title' => $project->title,
            'running' => $project->pendingDatasets()->whereIn('status', ['uploaded', 'validating'])->exists(),
            'fingerprint' => $this->fingerprint($project),
        ]);
    }

    public function fingerprint(Project $project): string
    {
        $fresh = $project->fresh();

        return md5(json_encode([
            $fresh->workflow_status->value,
            $fresh->workflow_changed_at?->getTimestamp(),
            $project->papers()->count(),
            $project->papers()->max('updated_at'),
            $project->deliveredDocuments()->max('delivered_at'),
        ]));
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:10', 'max:500'],
            'discipline' => ['required', Rule::in(self::DISCIPLINES)],
            'degree_level' => ['required', Rule::enum(DegreeLevel::class)],
            'research_type' => ['required', Rule::enum(ResearchType::class)],
            'citation_style' => ['required', Rule::enum(CitationStyle::class)],
            'word_count_target' => ['nullable', 'integer', 'min:1000', 'max:150000'],
            'description' => ['nullable', 'string', 'max:3000'],
            'institution' => ['nullable', 'string', 'max:255'],
            'guidelines' => ['nullable', 'string', 'max:5000'],
            'writer_notes' => ['nullable', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date', $project ? 'after_or_equal:'.min(now()->toDateString(), $project->deadline?->toDateString() ?? now()->toDateString()) : 'after:today'],
        ], [
            'title.min' => 'Give your project a descriptive title (at least 10 characters).',
        ]);
    }

    /** own, suggested, or suggested_edited (a suggestion the researcher changed before saving). */
    private function titleSource(string $title, ?string $suggested): string
    {
        if (! $suggested) {
            return 'own';
        }
        if (trim($title) === trim($suggested)) {
            return 'suggested';
        }
        similar_text(mb_strtolower($title), mb_strtolower($suggested), $percent);

        return $percent >= 60 ? 'suggested_edited' : 'own';
    }

    private function matchDiscipline(?string $value): string
    {
        if (! $value) {
            return self::DISCIPLINES[0];
        }

        foreach (self::DISCIPLINES as $d) {
            if (str_contains(strtolower($value), strtolower(strtok($d, ' &,')))) {
                return $d;
            }
        }

        return 'Other';
    }
}
