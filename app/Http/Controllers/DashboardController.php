<?php

namespace App\Http\Controllers;

use App\Enums\WorkflowStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (! $user->role) {
            return redirect()->route('onboarding');
        }

        $projects = $user->projects()->withCount('papers')->latest('updated_at')->get();
        $attention = $projects->filter(fn (Project $p) => $p->workflow_status->needsResearcher())->values();
        $featured = $attention->first(fn (Project $p) => $p->workflow_status === WorkflowStatus::Stage1Delivered)
            ?? $projects->first(fn (Project $p) => ! in_array($p->workflow_status, [WorkflowStatus::Draft, WorkflowStatus::Completed], true))
            ?? ($projects->count() > 2 ? $projects->first() : null);
        $cards = $projects->reject(fn (Project $p) => $featured && $p->is($featured))->take(2);

        // The researcher's own notifications, never the internal pipeline log.
        $events = $user->notifications()->latest()->limit(3)->get();

        $hour = now($user->timezone ?: config('app.timezone'))->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        return view('dashboard', [
            'user' => $user,
            'projects' => $projects,
            'cards' => $cards,
            'featured' => $featured,
            'attention' => $attention,
            'events' => $events,
            'greeting' => $greeting,
            'nextStep' => $this->nextStep($attention->first() ?? $projects->first()),
        ]);
    }

    private function nextStep(?Project $project): array
    {
        if (! $project) {
            return ['text' => 'Create a project and upload your papers', 'href' => route('projects.new'), 'icon' => 'add'];
        }

        $title = Str::limit($project->title, 40);

        return match ($project->workflow_status) {
            WorkflowStatus::Draft => ['text' => "Finish setting up • {$title}", 'href' => route('projects.setup.sources', $project), 'icon' => 'upload_file'],
            WorkflowStatus::Stage1Delivered => ['text' => "Upload your data • {$title}", 'href' => route('projects.data', $project), 'icon' => 'upload'],
            WorkflowStatus::NeedsInfo => ['text' => "Reply to our team • {$title}", 'href' => route('projects.show', $project), 'icon' => 'help'],
            WorkflowStatus::Completed => ['text' => "Download your thesis • {$title}", 'href' => route('projects.show', $project), 'icon' => 'download'],
            default => ['text' => "Follow progress • {$title}", 'href' => route('projects.show', $project), 'icon' => 'event'],
        };
    }
}
