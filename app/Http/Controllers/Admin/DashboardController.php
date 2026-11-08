<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\AiCall;
use App\Models\Paper;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Platform overview: accounts, projects, AI spend and anything that needs an administrator. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $monthStart = now()->startOfMonth();
        $lastMonth = [now()->subMonthNoOverflow()->startOfMonth(), $monthStart];

        $statusCounts = Project::query()->pluck('workflow_status')->countBy(fn (WorkflowStatus $s) => match (true) {
            $s === WorkflowStatus::Completed => 'completed',
            $s->needsAdmin() => 'queue',
            in_array($s, [WorkflowStatus::NeedsInfo, WorkflowStatus::OnHold], true) => 'paused',
            default => 'researcher',
        });

        $daily = AiCall::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('date(created_at) as day, sum(cost) as cost, count(*) as calls')
            ->groupBy('day')->get()->keyBy(fn ($r) => substr((string) $r->day, 0, 10));

        $days = collect(range(29, 0))->map(function ($ago) use ($daily) {
            $day = now()->subDays($ago)->toDateString();

            return ['day' => $day, 'cost' => (float) ($daily[$day]->cost ?? 0), 'calls' => (int) ($daily[$day]->calls ?? 0)];
        });

        return view('admin.dashboard', [
            'users' => [
                'total' => User::count(),
                'new' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'active' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
            ],
            'projects' => [
                'total' => $statusCounts->sum(),
                'queue' => $statusCounts->get('queue', 0),
                'paused' => $statusCounts->get('paused', 0),
                'researcher' => $statusCounts->get('researcher', 0),
                'completed' => $statusCounts->get('completed', 0),
            ],
            'papers' => Paper::count(),
            'revenue' => [
                'month' => Payment::where('status', Payment::SUCCESS)->where('paid_at', '>=', $monthStart)->sum('amount') / 100,
                'total' => Payment::where('status', Payment::SUCCESS)->sum('amount') / 100,
                'count' => Payment::where('status', Payment::SUCCESS)->where('paid_at', '>=', $monthStart)->count(),
            ],
            'cost' => [
                'month' => (float) AiCall::where('created_at', '>=', $monthStart)->sum('cost'),
                'last' => (float) AiCall::whereBetween('created_at', $lastMonth)->sum('cost'),
                'calls' => AiCall::where('created_at', '>=', $monthStart)->count(),
                'errors' => AiCall::where('created_at', '>=', $monthStart)->where('status', 'error')->count(),
            ],
            'days' => $days,
            'failedJobs' => DB::table('failed_jobs')->count(),
            // Jobs nobody has picked up for 5 minutes: the queue worker is not running (database queue only;
            // Horizon reports this itself for Redis).
            'stalledJobs' => config('queue.default') === 'database'
                ? DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(5)->getTimestamp())->count()
                : 0,
            'dueSoon' => Project::with('user:id,name')->whereNotIn('workflow_status', [WorkflowStatus::Draft->value, WorkflowStatus::Completed->value])
                ->whereNotNull('deadline')->where('deadline', '<=', now()->addDays(14)->toDateString())->orderBy('deadline')->limit(6)->get(),
            'queue' => Project::with('user:id,name')->whereIn('workflow_status', array_map(fn ($s) => $s->value, array_filter(WorkflowStatus::cases(), fn ($s) => $s->needsAdmin())))->orderBy('workflow_changed_at')->limit(6)->get(),
            'recentUsers' => User::withCount('projects')->latest('id')->limit(6)->get(),
            'byLevel' => Project::query()->pluck('degree_level')->countBy(fn ($l) => $l?->shortLabel() ?? 'Unknown'),
            'log' => AdminLog::with('admin:id,name')->latest('id')->limit(6)->get(),
        ]);
    }
}
