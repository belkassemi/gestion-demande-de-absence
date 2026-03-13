<?php

namespace App\Http\Controllers;

use App\Models\AbsenceType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AbsenceTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AbsenceType::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'requires_document'  => 'boolean',
            'color'              => 'nullable|string|max:7',
        ]);

        $type = AbsenceType::create($validated);
        return response()->json($type, 201);
    }

    public function show(AbsenceType $absenceType): JsonResponse
    {
        return response()->json($absenceType);
    }

    public function update(Request $request, AbsenceType $absenceType): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'requires_document'  => 'sometimes|boolean',
            'color'              => 'nullable|string|max:7',
        ]);

        $absenceType->update($validated);
        return response()->json($absenceType->fresh());
    }

    public function destroy(AbsenceType $absenceType): JsonResponse
    {
        $absenceType->delete();
        return response()->json(['message' => 'Absence type deleted.']);
    }
}
