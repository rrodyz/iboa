<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Auth::user()
            ->notifications()
            ->paginate(20);

        $unreadCount = Auth::user()->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Return unread count + recent 8 notifications for the header bell (AJAX).
     *
     * [PERF-FIX-04] Was 2 queries (list + count). Now 1 query: derive the unread
     * count from the fetched collection. If ALL 8 fetched rows are unread it is
     * possible the real count is higher, so we fire a cheap COUNT() only in that
     * edge case (stays ≤ 2 queries in the worst case, usually 1).
     */
    public function recent(): JsonResponse
    {
        $user          = Auth::user();
        $notifications = $user->notifications()->limit(8)->get();

        $unreadInWindow = $notifications->whereNull('read_at')->count();

        // Only run a second COUNT query when every fetched row is unread —
        // meaning there may be additional unread notifications beyond the window.
        $unread = ($unreadInWindow === $notifications->count() && $notifications->isNotEmpty())
            ? $user->unreadNotifications()->count()
            : $unreadInWindow;

        $items = $notifications->map(fn ($n) => [
            'id'         => $n->id,
            'read'       => ! is_null($n->read_at),
            'type'       => $n->data['type']    ?? 'info',
            'icon'       => $n->data['icon']    ?? 'bell',
            'color'      => $n->data['color']   ?? 'indigo',
            'title'      => $n->data['title']   ?? '',
            'message'    => $n->data['message'] ?? '',
            'url'        => $n->data['url']     ?? null,
            'created_at' => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread' => $unread,
            'items'  => $items,
        ]);
    }

    /**
     * Mark one notification as read and redirect to its URL.
     */
    public function markRead(string $id): RedirectResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard');
        return redirect($url);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        Auth::user()->notifications()->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }
}
