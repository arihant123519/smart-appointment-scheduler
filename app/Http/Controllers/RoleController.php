<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role & permission management (PRD §4 RBAC). Lets a system administrator define
 * roles and toggle the exact permissions each role holds.
 *
 * Guard rails:
 *  - the protected "system_admin" role always keeps every permission and cannot
 *    be deleted (prevents an accidental lock-out),
 *  - a role with users assigned cannot be deleted.
 */
class RoleController extends Controller
{
    private const PROTECTED = 'system_admin';

    /** Human-friendly grouping of permissions for the UI. */
    private function groupedPermissions(): array
    {
        $groups = [];
        foreach (Permission::orderBy('name')->get() as $permission) {
            // Group by the noun after the verb, e.g. "manage patients" -> "Patients".
            $parts = explode(' ', $permission->name, 2);
            $group = Str::headline($parts[1] ?? $parts[0]);
            $groups[$group][] = $permission;
        }
        ksort($groups);

        return $groups;
    }

    public function index(): View
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $groups = $this->groupedPermissions();
        $role = new Role;
        $assigned = [];

        return view('roles.create', compact('groups', 'role', 'assigned'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name', 'regex:/^[a-z0-9_]+$/'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.regex' => 'Use lowercase letters, numbers and underscores only (e.g. nurse_lead).',
        ]);

        $role = Role::create(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        AuditLog::record('role_created', $role);

        return redirect()->route('roles.index')->with('success', "Role “{$role->name}” created.");
    }

    public function edit(Role $role): View
    {
        $groups = $this->groupedPermissions();
        $assigned = $role->permissions->pluck('name')->all();

        return view('roles.edit', compact('groups', 'role', 'assigned'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // The protected admin role always retains every permission.
        if ($role->name === self::PROTECTED) {
            $role->syncPermissions(Permission::pluck('name')->all());

            return redirect()->route('roles.index')
                ->with('error', 'The system_admin role always keeps all permissions and was left unchanged.');
        }

        $role->syncPermissions($data['permissions'] ?? []);
        AuditLog::record('role_updated', $role);

        return redirect()->route('roles.index')->with('success', "Role “{$role->name}” updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === self::PROTECTED) {
            return back()->with('error', 'The system_admin role cannot be deleted.');
        }
        if ($role->users()->count() > 0) {
            return back()->with('error', 'This role still has users assigned. Reassign them first.');
        }

        $name = $role->name;
        $role->delete();
        AuditLog::record('role_deleted', null, ['name' => $name]);

        return redirect()->route('roles.index')->with('success', "Role “{$name}” deleted.");
    }
}
