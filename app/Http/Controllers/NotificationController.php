<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->notifications()->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    /** Lightweight JSON feed for the real-time notification poller. */
    public function feed(): JsonResponse
    {
        $user = auth()->user();

        $items = $user->unreadNotifications()->latest()->limit(10)->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'icon' => $n->data['icon'] ?? 'fi-rr-bell',
            ])->values();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
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
