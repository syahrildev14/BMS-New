<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dapur;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::with(['roles', 'dapur', 'supplier'])->get(),
            'roles' => Role::all(),
            'dapurs' => Dapur::all(),
            'suppliers' => Supplier::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'dapur_id' => ['nullable', 'integer', 'exists:dapurs,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'is_active' => ['boolean'],
        ]);

        $this->assertDapurScopeConsistency($validated['role'], $validated['dapur_id'] ?? null);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'dapur_id' => $validated['dapur_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'string', 'exists:roles,name'],
            'dapur_id' => ['nullable', 'integer', 'exists:dapurs,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'is_active' => ['boolean'],
        ]);

        $this->assertDapurScopeConsistency($validated['role'], $validated['dapur_id'] ?? null);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'dapur_id' => $validated['dapur_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('success', 'User berhasil dihapus.');
    }

    /**
     * A role that carries orders.manage is meant for Dapur-portal users —
     * Dapur\OrderController and its routes scope every query by
     * $user->dapur_id. Allowing that role to be assigned with dapur_id left
     * null doesn't just silently scope the user out of everything; it also
     * crashes with an uncaught FK-constraint QueryException (orders.dapur_id
     * is NOT NULL) the moment that user submits their first order.
     */
    private function assertDapurScopeConsistency(string $roleName, ?int $dapurId): void
    {
        $role = Role::findByName($roleName);

        if ($role->hasPermissionTo('orders.manage') && ! $dapurId) {
            throw ValidationException::withMessages([
                'dapur_id' => "Role \"{$roleName}\" punya akses Dapur Portal (orders.manage) — dapur_id wajib diisi.",
            ]);
        }
    }
}
