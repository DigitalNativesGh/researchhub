<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Failed queue jobs: inspect, retry or discard them. */
class JobController extends Controller
{
    public function index(): View
    {
        $jobs = DB::table('failed_jobs')->latest('id')->paginate(20);

        $jobs->getCollection()->transform(function ($job) {
            $payload = json_decode($job->payload, true) ?: [];
            $job->name = class_basename($payload['displayName'] ?? 'Unknown job');
            $job->summary = strtok((string) $job->exception, "\n");

            return $job;
        });

        return view('admin.jobs', ['jobs' => $jobs]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AdminLog::record('job.retried', "Retried failed job {$uuid}");

        return back()->with('status', 'Job pushed back onto the queue.');
    }

    public function destroy(string $uuid): RedirectResponse
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        AdminLog::record('job.deleted', "Discarded failed job {$uuid}");

        return back()->with('status', 'Failed job discarded.');
    }

    public function flush(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->delete();
        AdminLog::record('job.flushed', "Discarded all {$count} failed jobs");

        return back()->with('status', "Discarded {$count} failed ".Str::plural('job', $count).'.');
    }
}
