<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->notifications()->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    public function read(string $id): RedirectResponse
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $n->data['url'] ?? false
            ? redirect($n->data['url'])
            : back();
    }

    public function readAll(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
