<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\ProjectEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Administrator actions, and the pipeline activity of every project. */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = $request->query('tab') === 'pipeline' ? 'pipeline' : 'admin';

        return view('admin.activity', [
            'tab' => $tab,
            'entries' => $tab === 'admin'
                ? AdminLog::with('admin:id,name,email')->latest('id')->paginate(30)->withQueryString()
                : ProjectEvent::with('project:id,title,user_id', 'project.user:id,name')->latest('id')->paginate(30)->withQueryString(),
        ]);
    }
}
