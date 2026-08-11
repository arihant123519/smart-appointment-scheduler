<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const PROTECTED_ROLE = 'system_admin';

    public function index(Request $request): View
    {
        $query = User::with('roles')->forCurrentClinic()->orderBy('name');

        if ($request->filled('role')) {
            $query->role($request->string('role')->toString());
        }
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        $users = $query->get();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'min:8'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $this->guardProtectedRoleAssignment($data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'must_change_password' => true,
            'clinic_id' => auth()->user()->clinic_id,
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->authorizeClinic($user);
        $roles = Role::orderBy('name')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeClinic($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'min:8'],
        ]);

        $this->guardProtectedRoleAssignment($data['role'], $user);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password']), 'must_change_password' => true]);
        }
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $this->authorizeClinic($user);
        if ($user->hasRole(self::PROTECTED_ROLE) && ! auth()->user()->hasRole(self::PROTECTED_ROLE)) {
            return back()->with('error', 'Only a system administrator can remove another system administrator.');
        }
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User removed.');
    }

    /** Route-model-binding by id doesn't scope by clinic — enforce it explicitly here. */
    private function authorizeClinic(User $user): void
    {
        $actor = auth()->user();
        if ($actor->hasRole(self::PROTECTED_ROLE)) {
            return;
        }
        abort_unless($user->clinic_id === $actor->clinic_id, 404);
    }

    /**
     * Only an existing system_admin may grant (or leave in place) the
     * system_admin role — otherwise any clinic_admin holding "manage users"
     * could hand themselves or anyone else full cross-clinic superuser access.
     */
    private function guardProtectedRoleAssignment(string $role, ?User $target = null): void
    {
        $actor = auth()->user();
        $grantingProtected = $role === self::PROTECTED_ROLE;
        $targetAlreadyProtected = $target?->hasRole(self::PROTECTED_ROLE) ?? false;

        if (($grantingProtected || $targetAlreadyProtected) && ! $actor->hasRole(self::PROTECTED_ROLE)) {
            abort(403, 'Only a system administrator can assign or modify the system_admin role.');
        }
    }
}
