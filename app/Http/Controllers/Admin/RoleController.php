<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/roles/index', [
            'roles' => Role::with('permissions')->get(),
            'permissions' => Permission::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Role berhasil dibuat.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $newPermissions = $validated['permissions'] ?? [];

        if ($role->hasPermissionTo('roles.manage') && ! in_array('roles.manage', $newPermissions, true)) {
            $this->assertRolesManageStaysCovered($role);
        }

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($newPermissions);

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'name' => "Role \"{$role->name}\" masih dipakai oleh user, tidak bisa dihapus.",
            ]);
        }

        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }

    /**
     * Guards against a self-lockout: removing roles.manage from $role must
     * not leave the system with zero roles.manage holders that actually have
     * a user assigned — a role nobody holds can't be used to undo the
     * mistake. $role itself is excluded from the "still covered" check since
     * we're evaluating the edit that's about to strip its own permission.
     */
    private function assertRolesManageStaysCovered(Role $role): void
    {
        $stillCovered = Role::where('id', '!=', $role->id)
            ->get()
            ->filter(fn (Role $other) => $other->hasPermissionTo('roles.manage') && $other->users()->exists())
            ->isNotEmpty();

        if (! $stillCovered) {
            throw ValidationException::withMessages([
                'permissions' => "Tidak bisa menghapus permission roles.manage dari role \"{$role->name}\" — tidak ada role lain (dengan user aktif) yang masih punya akses mengelola role. Sistem akan terkunci dari manajemen role.",
            ]);
        }
    }
}
