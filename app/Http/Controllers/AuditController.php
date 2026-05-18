<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::with('user')
            ->when($request->search, fn($q, $s) =>
                $q->where(function ($q) use ($s) {
                    $q->where('user_name', 'like', "%$s%")
                      ->orWhere('model_type', 'like', "%$s%")
                      ->orWhere('url', 'like', "%$s%");
                })
            )
            ->when($request->action, fn($q, $a) => $q->where('action', $a))
            ->when($request->user_id, fn($q, $uid) => $q->where('user_id', $uid))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $users   = User::orderBy('name')->get(['id', 'name']);
        $actions = ['created', 'updated', 'deleted', 'login', 'logout', 'validated', 'sent', 'exported'];

        return view('audit.index', compact('logs', 'users', 'actions'));
    }
}
