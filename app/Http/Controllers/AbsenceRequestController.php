<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AbsenceRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = AbsenceRequest::with(['user', 'absenceType', 'approvals'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->absence_type_id, fn($q) => $q->where('absence_type_id', $request->absence_type_id))
            ->latest();

        // Scope by role
        if (in_array($user->role, ['employee'])) {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'chef') {
            $query->whereHas('user', fn($q) => $q->where('manager_id', $user->id));
        }
        // rh, directeur, admin can see all

        return response()->json($query->paginate(20));
    }

    /**
     * GET /absence-requests/my-stats
     * Statistics for the authenticated employee.
     */
    public function myStats(): JsonResponse
    {
        $user = Auth::user();
        $year = now()->year;

        $requests = $user->absenceRequests()->whereYear('created_at', $year)->get();

        $totalDays    = $requests->where('status', 'approved')->sum('days_count');
        $pending      = $requests->where('status', 'pending')->count();
        $approved     = $requests->where('status', 'approved')->count();
        $rejected     = $requests->where('status', 'rejected')->count();

        $byType = $requests->where('status', 'approved')
            ->groupBy('absence_type_id')
            ->map(fn($g) => ['days' => $g->sum('days_count'), 'count' => $g->count()])
            ->toArray();

        $monthly = $requests->groupBy(fn($r) => $r->created_at->format('Y-m'))
            ->map(fn($g) => $g->count())
            ->toArray();

        return response()->json([
            'year'       => $year,
            'total_days' => $totalDays,
            'pending'    => $pending,
            'approved'   => $approved,
            'rejected'   => $rejected,
            'by_type'    => $byType,
            'monthly'    => $monthly,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'absence_type_id'  => 'required|exists:absence_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'nullable|string',
            'document'         => 'nullable|file|max:5120', // Max 5MB file
        ]);

        $data = $validated;
        
        // Ensure days_count is set correctly based on dates if not passed (though the Model or Observer could do this, we should make sure it's set in the API or calculated here if the Model requires it.
        // For simplicity, let's assume the frontend passes days_count or we calculate it here if it's required.
        // Actually the model doesn't automatically calculate it. Let's calculate simple business days if not provided.
        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        $data['days_count'] = $start->diffInDaysFiltered(function(\Carbon\Carbon $date) {
            return !$date->isWeekend();
        }, $end) + 1; // inclusive
        
        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('absence_documents', 'public');
        }
        
        unset($data['document']); // we mapped this to document_path or it's not a db column directly

        $absenceRequest = AbsenceRequest::create($data);
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
