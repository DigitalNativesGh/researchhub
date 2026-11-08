<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    private const FILTERS = ['all' => 'All users', 'admins' => 'Administrators', 'suspended' => 'Suspended', 'unverified' => 'Unverified'];

    public function index(Request $request): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'all';
        $q = trim((string) $request->query('q'));
        $like = '%'.mb_strtolower($q).'%';

        $users = User::query()
            ->withCount('projects')
            ->withSum('aiCalls as ai_cost', 'cost')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like])->orWhereRaw('lower(institution) like ?', [$like])))
            ->when($filter === 'admins', fn ($query) => $query->where('is_admin', true))
            ->when($filter === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($filter === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'filters' => self::FILTERS, 'filter' => $filter, 'q' => $q]);
    }

    public function show(User $user): View
    {
        $user->loadCount('projects');

        return view('admin.users.show', [
            'user' => $user,
            'projects' => $user->projects()->withCount('papers')->latest('updated_at')->get(),
            'usage' => [
                'cost' => (float) $user->aiCalls()->sum('cost'),
                'month' => (float) $user->aiCalls()->where('created_at', '>=', now()->startOfMonth())->sum('cost'),
                'calls' => $user->aiCalls()->count(),
                'errors' => $user->aiCalls()->where('status', 'error')->count(),
            ],
            'payments' => $user->payments()->with('project:id,title')->latest('id')->limit(10)->get(),
            'log' => AdminLog::with('admin:id,name')->where('subject_type', 'User')->where('subject_id', $user->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own administrator access.');
        }

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();
        AdminLog::record($user->is_admin ? 'user.promoted' : 'user.demoted', ($user->is_admin ? 'Granted administrator access to ' : 'Revoked administrator access from ').$user->email, $user);

        return back()->with('status', $user->is_admin ? "{$user->name} is now an administrator." : "{$user->name} is no longer an administrator.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason'] ?? null])->save();

        // End their other sessions now rather than on their next request.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        AdminLog::record('user.suspended', "Suspended {$user->email}", $user, array_filter(['reason' => $data['reason'] ?? null]));

        return back()->with('status', "{$user->name} has been suspended and signed out.");
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        AdminLog::record('user.unsuspended', "Restored access for {$user->email}", $user);

        return back()->with('status', "{$user->name} can sign in again.");
    }

    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            AdminLog::record('user.verified', "Marked {$user->email} as verified", $user);
        }

        return back()->with('status', 'Email address marked as verified.');
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $result = Password::sendResetLink(['email' => $user->email]);
        AdminLog::record('user.password_reset', "Sent a password reset link to {$user->email}", $user);

        return back()->with($result === Password::RESET_LINK_SENT ? 'status' : 'error', __($result));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account from the admin dashboard.');
        }

        $request->validate(['confirm_email' => ['required', 'in:'.$user->email]], ['confirm_email.in' => 'Type the user\'s email address exactly to confirm.']);

        foreach ($user->projects as $project) {
            $project->purgeFiles();
            $project->delete();
        }
        $user->deleteAvatar();
        AdminLog::record('user.deleted', "Deleted {$user->email} and their projects", $user, ['name' => $user->name]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User and all of their projects deleted.');
    }
}
