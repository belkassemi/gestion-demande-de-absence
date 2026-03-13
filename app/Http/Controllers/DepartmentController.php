<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::with('director', 'users')->get();
        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'director_id' => 'nullable|exists:users,id',
        ]);

        $department = Department::create($validated);
        return response()->json($department->load('director'), 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json($department->load('director', 'users'));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'director_id' => 'nullable|exists:users,id',
        ]);

        $department->update($validated);
        return response()->json($department->fresh('director'));
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }

    public function statistics(Department $department): JsonResponse
    {
        return response()->json($department->getStatistics());
    }
}
