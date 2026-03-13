<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AbsenceRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = AbsenceRequest::with(['user', 'absenceType', 'approvals'])
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return response()->json($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'absence_type_id'  => 'required|exists:absence_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'nullable|string',
        ]);

        $absenceRequest = AbsenceRequest::create($validated);
        $absenceRequest->submit();

        AuditLog::log('created', 'AbsenceRequest', $absenceRequest->id);

        return response()->json($absenceRequest->load('user', 'absenceType'), 201);
    }

    public function show(AbsenceRequest $absenceRequest): JsonResponse
    {
        return response()->json(
            $absenceRequest->load('user', 'absenceType', 'approvals.approver', 'documents')
        );
    }

    public function update(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'start_date'  => 'sometimes|date',
            'end_date'    => 'sometimes|date|after_or_equal:start_date',
            'reason'      => 'nullable|string',
        ]);

        $absenceRequest->updateData($validated);

        AuditLog::log('updated', 'AbsenceRequest', $absenceRequest->id);

        return response()->json($absenceRequest->fresh('user', 'absenceType'));
    }

    public function destroy(AbsenceRequest $absenceRequest): JsonResponse
    {
        AuditLog::log('deleted', 'AbsenceRequest', $absenceRequest->id);
        $absenceRequest->delete();
        return response()->json(['message' => 'Request deleted.']);
    }

    public function cancel(AbsenceRequest $absenceRequest): JsonResponse
    {
        $absenceRequest->cancel();
        AuditLog::log('cancelled', 'AbsenceRequest', $absenceRequest->id);
        return response()->json(['message' => 'Request cancelled.', 'request' => $absenceRequest]);
    }

    public function approve(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'level'   => 'required|integer|min:1',
            'comment' => 'nullable|string',
        ]);

        $absenceRequest->approve($validated['level'], $validated['comment'] ?? '');

        AuditLog::log('approved', 'AbsenceRequest', $absenceRequest->id);

        return response()->json(['message' => 'Request approved.', 'request' => $absenceRequest->fresh()]);
    }

    public function reject(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'level'   => 'required|integer|min:1',
            'comment' => 'nullable|string',
        ]);

        $absenceRequest->reject($validated['level'], $validated['comment'] ?? '');

        AuditLog::log('rejected', 'AbsenceRequest', $absenceRequest->id);

        return response()->json(['message' => 'Request rejected.', 'request' => $absenceRequest->fresh()]);
    }
}
