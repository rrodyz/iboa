<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status']);

        $users = User::with('roles')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('job_title', 'like', "%$search%");
                });
            })
            ->when(isset($filters['role']) && $filters['role'] !== '', function ($q) use ($filters) {
                $q->whereHas('roles', fn($r) => $r->where('name', $filters['role']));
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'filters'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $roles = Role::orderBy('name')->get();
        return view('users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->safe()->except(['role', 'password_confirmation']);
        $data['is_active']  = $request->boolean('is_active', true);
        $data['company_id'] = auth()->user()->company_id;

        $user = User::create($data);
        $user->syncRoles([$request->role]);

        $this->audit->log('created', $user, [], [
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $request->role,
        ]);

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'Utilisateur créé avec succès.');
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load('roles');
        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $user->load('roles');
        $roles = Role::orderBy('name')->get();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $old = $user->only(['name', 'email', 'is_active', 'job_title']);
        $oldRole = $user->roles->pluck('name')->first();

        $data = $request->safe()->except(['role', 'password', 'password_confirmation']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);
        $user->syncRoles([$request->role]);

        $this->audit->log('updated', $user,
            array_merge($old, ['role' => $oldRole]),
            array_merge($user->only(['name', 'email', 'is_active', 'job_title']), ['role' => $request->role])
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'Utilisateur mis à jour.');
    }

    public function toggleActive(User $user)
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => !$user->is_active]);

        $this->audit->log('updated', $user, ['is_active' => !$user->is_active], ['is_active' => $user->is_active]);

        $msg = $user->is_active ? 'Utilisateur activé.' : 'Utilisateur désactivé.';
        return back()->with('success', $msg);
    }
}
