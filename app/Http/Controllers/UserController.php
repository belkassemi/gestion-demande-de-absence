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
     * GET /api/admin/users
     *
     * List all users with optional filters.
     *
     * Middleware: auth:sanctum, role:admin
     * Headers: Authorization: Bearer {token}, Accept: application/json
     *
     * Query Params: role, department_id, service_id
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with('department', 'service', 'chefService')
            ->when($request->role,          fn($q) => $q->where('role', $request->role))
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->service_id,    fn($q) => $q->where('service_id', $request->service_id))
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    /**
     * POST /api/admin/users
     *
     * Create a new user.
     *
     * Request Body:
     *   {
     *     "name": "Jane Doe",
     *     "email": "jane@example.com",
     *     "password": "password123",
     *     "role": "employee",
     *     "department_id": 1,
     *     "service_id": 2,
     *     "chef_service_id": 3,
     *     "is_active": true
     *   }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users',
            'password'        => 'required|string|min:8',
            'role'            => 'required|in:employee,chef_service,directeur',
            'department_id'   => 'nullable|exists:departments,id',
            'service_id'      => 'nullable|exists:services,id',
            'chef_service_id' => 'nullable|exists:users,id',
            'is_active'       => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        AuditLog::log('user_created', 'User', $user->id);

        return response()->json(['message' => 'Utilisateur créé avec succès.', 'user' => $user], 201);
    }

    /**
     * GET /api/admin/users/{id}
     *
     * Show a single user with relations.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('department', 'service', 'chefService', 'teamMembers'));
    }

    /**
     * PUT /api/admin/users/{id}
     *
     * Update user details. Unprovided fields are ignored.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'email'           => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'            => 'sometimes|in:employee,chef_service,directeur,admin',
            'department_id'   => 'nullable|exists:departments,id',
            'service_id'      => 'nullable|exists:services,id',
            'chef_service_id' => 'nullable|exists:users,id',
            'is_active'       => 'boolean',
            'password'        => 'sometimes|string|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);
        AuditLog::log('user_updated', 'User', $user->id);

        return response()->json(['message' => 'Utilisateur mis à jour.', 'user' => $user->fresh('department', 'service', 'chefService')]);
    }

    /**
     * DELETE /api/admin/users/{id}
     *
     * Delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        AuditLog::log('user_deleted', 'User', $user->id);
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
