<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /admin/users
     * List all users with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with('department', 'manager')
            ->when($request->role,          fn($q) => $q->where('role', $request->role))
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    /**
     * POST /admin/users
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8',
            'role'          => 'required|in:employee,chef,rh,directeur,admin',
            'department_id' => 'nullable|exists:departments,id',
            'manager_id'    => 'nullable|exists:users,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        AuditLog::log('user_created', 'User', $user->id);

        return response()->json(['message' => 'Utilisateur créé avec succès.', 'user' => $user], 201);
    }

    /**
     * GET /admin/users/{id}
     * Show a single user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('department', 'manager', 'subordinates'));
    }

    /**
     * PUT /admin/users/{id}
     * Update user details.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'          => 'sometimes|in:employee,chef,rh,directeur,admin',
            'department_id' => 'nullable|exists:departments,id',
            'manager_id'    => 'nullable|exists:users,id',
            'password'      => 'sometimes|string|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);
        AuditLog::log('user_updated', 'User', $user->id);

        return response()->json(['message' => 'Utilisateur mis à jour.', 'user' => $user->fresh('department', 'manager')]);
    }

    /**
     * DELETE /admin/users/{id}
     * Delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        AuditLog::log('user_deleted', 'User', $user->id);
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
