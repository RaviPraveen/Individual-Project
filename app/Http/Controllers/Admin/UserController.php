<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    // ─── Index ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', '%'.$request->input('name').'%'))
            ->when($request->filled('email'), fn ($q) => $q->where('email', 'like', '%'.$request->input('email').'%'))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_active', $request->input('status') === 'active');
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    // ─── Create / Store ───────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role'                  => ['required', Rule::in(['admin', 'cashier'])],
            'password'              => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'role'     => $validated['role'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    // ─── Edit / Update ────────────────────────────────────────────────────────

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'                  => ['required', Rule::in(['admin', 'cashier'])],
            'password'              => ['nullable', 'confirmed', Password::min(8)],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        $isActive = $request->boolean('is_active', true);

        // Safeguard: cannot demote or deactivate own account
        if ($user->id === $request->user()->id) {
            if ($validated['role'] !== 'admin') {
                return back()->with('error', 'You cannot change your own role.');
            }
            if (! $isActive) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
        }

        // Safeguard: must always have at least one active admin
        if ($user->role === 'admin' && ($validated['role'] !== 'admin' || ! $isActive)) {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otherActiveAdmins) {
                return back()->with('error', 'Cannot demote or deactivate the last active admin account.');
            }
        }

        $data = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'is_active' => $isActive,
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Safeguard: cannot delete own account
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Safeguard: must always have at least one active admin
        if ($user->role === 'admin' && $user->is_active) {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otherActiveAdmins) {
                return back()->with('error', 'Cannot delete the last active admin account.');
            }
        }

        // Soft-delete pattern: deactivate if the user has related records
        if ($user->hasRelatedRecords()) {
            $user->update(['is_active' => false]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User has related records and was deactivated instead of deleted.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    // ─── Force Password Reset ─────────────────────────────────────────────────

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'temp_password'                  => ['required', 'confirmed', Password::min(8)],
            'temp_password_confirmation'     => ['required'],
        ]);

        $user->update([
            'password'             => Hash::make($request->input('temp_password')),
            'force_password_reset'  => true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Password for {$user->name} has been reset. They will be prompted to change it on next login.");
    }
}
