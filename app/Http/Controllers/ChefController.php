<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChefController extends Controller
{
    /**
     * GET /chef/pending-requests
     * Returns team requests waiting at level 1.
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $chef = Auth::user();

        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals'])
            ->whereHas('user', fn($q) => $q->where('manager_id', $chef->id))
            ->where('status', 'pending')
            ->where('current_level', 1)
            ->orderBy('created_at')
            ->get()
            ->map(function ($r) {
                $r->employee_total_days = $r->user->getTotalAbsenceDays();
                return $r;
            });

        return response()->json($requests);
    }

    /**
     * GET /chef/requests/{id}
     * Returns full details of a specific request with context.
     */
    public function showRequest(AbsenceRequest $absenceRequest): JsonResponse
    {
        $absenceRequest->load(['user.department', 'absenceType', 'approvals.approver', 'documents']);
        $absenceRequest->employee_total_days = $absenceRequest->user->getTotalAbsenceDays();

        return response()->json($absenceRequest);
    }

    /**
     * POST /chef/requests/{id}/review
     * Approve or reject at level 1.
     * Body: { action: 'approve'|'reject', comment?: string }
     */
    public function review(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'action'  => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'approve') {
            $absenceRequest->approve(1, $validated['comment'] ?? '');
            AuditLog::log('chef_approved', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            $message = 'Demande approuvée au niveau 1.';
        } else {
            if (empty($validated['comment'])) {
                return response()->json(['message' => 'Un commentaire est obligatoire pour le rejet.'], 422);
            }
            $absenceRequest->reject(1, $validated['comment']);
            AuditLog::log('chef_rejected', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            $message = 'Demande rejetée au niveau 1.';
        }

        return response()->json(['message' => $message, 'request' => $absenceRequest->fresh()]);
    }

    /**
     * GET /chef/team-calendar
     * Approved absences of the chef's team.
     */
    public function teamCalendar(Request $request): JsonResponse
    {
        $chef = Auth::user();

        $absences = AbsenceRequest::with(['user', 'absenceType'])
            ->whereHas('user', fn($q) => $q->where('manager_id', $chef->id))
            ->where('status', 'approved')
            ->when($request->month, function ($q) use ($request) {
                $q->whereMonth('start_date', $request->month)
                  ->orWhereMonth('end_date', $request->month);
            })
            ->when($request->year, function ($q) use ($request) {
                $q->whereYear('start_date', $request->year)
                  ->orWhereYear('end_date', $request->year);
            })
            ->get();

        return response()->json($absences);
    }

    /**
     * GET /chef/team-history
     * All requests from the team (all statuses).
     */
    public function teamHistory(Request $request): JsonResponse
    {
        $chef = Auth::user();

        $requests = AbsenceRequest::with(['user', 'absenceType', 'approvals'])
            ->whereHas('user', fn($q) => $q->where('manager_id', $chef->id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->absence_type_id, fn($q) => $q->where('absence_type_id', $request->absence_type_id))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->latest()
            ->paginate(20);

        return response()->json($requests);
    }
}
