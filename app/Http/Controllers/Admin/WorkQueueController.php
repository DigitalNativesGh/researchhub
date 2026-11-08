<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DegreeLevel;
use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Projects needing action, longest waiting first, with deadlines. "Waiting on the team" is the default view; the
 * researcher-side states (needs information, on hold) are one filter away.
 */
class WorkQueueController extends Controller
{
    public const DEADLINES = ['overdue' => 'Overdue', '7' => 'Due within 7 days', '30' => 'Due within 30 days'];

    public function index(Request $request): View
    {
        $team = array_values(array_filter(WorkflowStatus::cases(), fn (WorkflowStatus $s) => $s->needsAdmin()));
        $paused = [WorkflowStatus::NeedsInfo, WorkflowStatus::OnHold];

        $scope = $request->query('scope') === 'all' ? 'all' : 'team';
        $status = WorkflowStatus::tryFrom((string) $request->query('status'));
        $stage = in_array($request->query('stage'), ['1', '3'], true) ? (int) $request->query('stage') : null;
        $level = DegreeLevel::tryFrom((string) $request->query('level'));
        $discipline = in_array($request->query('discipline'), ProjectController::DISCIPLINES, true) ? $request->query('discipline') : null;
        $deadline = array_key_exists((string) $request->query('deadline'), self::DEADLINES) ? (string) $request->query('deadline') : null;

        $statuses = $status ? [$status] : ($scope === 'all' ? array_merge($team, $paused) : $team);

        $projects = Project::query()
            ->with(['user:id,name,email', 'editingAssignments' => fn ($q) => $q->whereNull('completed_at')])
            ->whereIn('workflow_status', array_map(fn ($s) => $s->value, $statuses))
            ->when($stage, fn ($q) => $q->whereIn('workflow_status', array_map(fn ($s) => $s->value, array_filter(WorkflowStatus::cases(), fn ($s) => $s->stage() === $stage))))
            ->when($level, fn ($q) => $q->where('degree_level', $level->value))
            ->when($discipline, fn ($q) => $q->where('discipline', $discipline))
            ->when($deadline === 'overdue', fn ($q) => $q->whereNotNull('deadline')->where('deadline', '<', now()->toDateString()))
            ->when(in_array($deadline, ['7', '30'], true), fn ($q) => $q->whereNotNull('deadline')->whereBetween('deadline', [now()->toDateString(), now()->addDays((int) $deadline)->toDateString()]))
            ->orderByRaw('workflow_changed_at is null')
            ->orderBy('workflow_changed_at')
            ->paginate(25)->withQueryString();

        $counts = Project::query()->whereIn('workflow_status', array_map(fn ($s) => $s->value, array_merge($team, $paused)))
            ->pluck('workflow_status')->countBy(fn (WorkflowStatus $s) => $s->value);

        return view('admin.queue', [
            'projects' => $projects,
            'counts' => $counts,
            'cards' => array_merge($team, $paused),
            'disciplines' => ProjectController::DISCIPLINES,
            'deadlines' => self::DEADLINES,
            'scope' => $scope,
            'filters' => compact('status', 'stage', 'level', 'discipline', 'deadline'),
        ]);
    }
}
