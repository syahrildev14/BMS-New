<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dapur;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'role' => ['required', 'string', 'exists:roles,name'],
            'dapur_id' => ['nullable', 'integer', 'exists:dapurs,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'is_active' => ['boolean'],
        ]);

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
}
