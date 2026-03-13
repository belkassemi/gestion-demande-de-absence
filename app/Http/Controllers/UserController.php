<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|max:255|unique:users',
            'password'      => 'required|string|min:8',
            'role'          => 'required|in:employee,manager,hr,admin',
            'department_id' => 'nullable|exists:departments,id',
            'manager_id'    => 'nullable|exists:users,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user
        ], 201);
    }
}
