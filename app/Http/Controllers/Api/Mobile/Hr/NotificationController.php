<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi di aplikasi HP, dari tabel notifikasi yang sama dengan lonceng web.
 * Aplikasi menentukan layar tujuan dari "category" (+ "meta"), bukan dari url web.
 */
class NotificationController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate(30);

        return $this->paginated($page, fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? 'Notifikasi',
            'message' => $n->data['message'] ?? '',
            'category' => $n->data['category'] ?? 'general',
            'meta' => $n->data['meta'] ?? (object) [],
            'web_url' => $n->data['url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return $this->message('Ditandai dibaca.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->message('Semua notifikasi ditandai telah dibaca.');
    }
}
