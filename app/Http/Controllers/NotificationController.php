<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        return view('notifications.index', [
            'notifications' => auth()->user()->notifications()->paginate(20),
        ]);
    }

    /** Lightweight unread count for the topbar bell to poll. */
    public function count(): JsonResponse
    {
        return response()->json(['count' => auth()->user()->unreadNotifications()->count()]);
    }

    /**
     * Mark a single notification read and forward the user to the page where they
     * can act on it (falls back to the full list if the link is missing).
     */
    public function read(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua notifikasi ditandai telah dibaca.');
    }
}
