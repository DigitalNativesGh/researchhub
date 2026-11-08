<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** In-app notifications (the bell). */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    /** Mark one as read and follow its link. Only links into this site are followed. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        $url = (string) ($notification->data['url'] ?? '');

        return str_starts_with($url, url('/')) ? redirect($url) : redirect()->route('dashboard');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
