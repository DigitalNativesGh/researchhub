<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DegreeLevel;
use App\Enums\DocumentType;
use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Project;
use App\Services\Workflow\DocumentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const FILTERS = ['all' => 'All', 'queue' => 'Waiting on the team', 'paused' => 'Needs info / on hold', 'researcher' => 'Waiting on researcher', 'completed' => 'Completed'];

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'all';
        $level = DegreeLevel::tryFrom((string) $request->query('level'));
        $q = trim((string) $request->query('q'));
        $like = '%'.mb_strtolower($q).'%';

        $projects = Project::query()
            ->with('user:id,name,email')
            ->withCount('papers')
            ->withSum('aiCalls as ai_cost', 'cost')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw('lower(title) like ?', [$like])
                ->orWhereHas('user', fn ($u) => $u->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like]))))
            ->when($level, fn ($query) => $query->where('degree_level', $level->value))
            ->when($status !== 'all', fn ($query) => $query->whereIn('workflow_status', array_map(fn ($s) => $s->value, array_filter(WorkflowStatus::cases(), fn (WorkflowStatus $w) => match ($status) {
                'queue' => $w->needsAdmin(),
                'paused' => in_array($w, [WorkflowStatus::NeedsInfo, WorkflowStatus::OnHold], true),
                'researcher' => $w->needsResearcher(),
                'completed' => $w === WorkflowStatus::Completed,
            }))))
            ->latest('updated_at')
            ->paginate(20)->withQueryString();

        return view('admin.projects.index', ['projects' => $projects, 'filters' => self::FILTERS, 'status' => $status, 'level' => $level, 'q' => $q]);
    }

    public function show(Project $project, DocumentStore $store): View
    {
        $project->load(['user']);
        $deliverables = $project->documents()->deliverables()->with('creator:id,name')->get();

        return view('admin.projects.show', [
            'project' => $project,
            'sources' => $project->sources()->get(),
            'guidelineFiles' => $project->guidelineFiles()->get(),
            'datasets' => $project->datasets()->orderBy('id')->get(),
            'latest' => [1 => $store->latestDeliverables($project, 1), 3 => $store->latestDeliverables($project, 3)],
            'history' => $deliverables->sortByDesc('id')->values(),
            'instrumentLabels' => $deliverables->where('type', DocumentType::Instrument)->pluck('label')->unique()->values()->all(),
            'assignment' => $project->editingAssignments()->whereNull('completed_at')->first(),
            'assignments' => $project->editingAssignments()->with('assigner:id,name')->get(),
            'transitions' => $project->transitions()->with('user:id,name')->get()->reverse()->values(),
            'audit' => $project->auditLogs()->with('user:id,name')->limit(50)->get(),
        ]);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:'.$project->reference()]], ['confirm.in' => 'Type the project reference exactly to confirm.']);

        AdminLog::record('project.deleted', "Deleted project {$project->reference()} \"".Str::limit($project->title, 80).'" owned by '.$project->user?->email, $project);
        $project->purgeFiles();
        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', 'Project deleted.');
    }
}
