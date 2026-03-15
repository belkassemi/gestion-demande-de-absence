<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RhController extends Controller
{
    /**
     * GET /rh/pending-requests
     * Returns requests at level 2 (chef already approved).
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals.approver', 'documents'])
            ->where('status', 'pending')
            ->where('current_level', 2)
            ->orderBy('created_at')
            ->get()
            ->map(function ($r) {
                $r->employee_total_days = $r->user->getTotalAbsenceDays();
                return $r;
            });

        return response()->json($requests);
    }

    /**
     * GET /rh/requests/{id}
     * Full details of a request for RH review.
     */
    public function showRequest(AbsenceRequest $absenceRequest): JsonResponse
    {
        $absenceRequest->load(['user.department', 'absenceType', 'approvals.approver', 'documents']);
        $absenceRequest->employee_total_days = $absenceRequest->user->getTotalAbsenceDays();

        return response()->json($absenceRequest);
    }

    /**
     * POST /rh/requests/{id}/review
     * Approve or reject at level 2.
     * Body: { action: 'approve'|'reject', comment?: string }
     */
    public function review(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'action'  => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'approve') {
            $absenceRequest->approve(2, $validated['comment'] ?? '');
            AuditLog::log('rh_approved', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            $message = 'Demande approuvée au niveau 2 (RH).';
        } else {
            if (empty($validated['comment'])) {
                return response()->json(['message' => 'Un commentaire est obligatoire pour le rejet.'], 422);
            }
            $absenceRequest->reject(2, $validated['comment']);
            AuditLog::log('rh_rejected', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            $message = 'Demande rejetée au niveau 2 (RH).';
        }

        return response()->json(['message' => $message, 'request' => $absenceRequest->fresh()]);
    }

    /**
     * GET /rh/employees/balances
     * Total absence days per employee for current year.
     */
    public function employeeBalances(Request $request): JsonResponse
    {
        $query = User::with('department')
            ->where('role', 'employee')
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id));

        $users = $query->get()->map(function ($u) {
            return [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'department'    => $u->department?->name,
                'total_days'    => $u->getTotalAbsenceDays(),
                'by_type'       => $u->absenceRequests()
                    ->where('status', 'approved')
                    ->whereYear('created_at', now()->year)
                    ->with('absenceType')
                    ->get()
                    ->groupBy('absenceType.name')
                    ->map(fn($group) => $group->sum('days_count')),
            ];
        });

        return response()->json($users);
    }

    /**
     * GET /rh/statistics
     * Statistics over a selectable period.
     */
    public function statistics(Request $request): JsonResponse
    {
        $from = $request->from ?? now()->startOfYear()->toDateString();
        $to   = $request->to   ?? now()->toDateString();

        $base = AbsenceRequest::whereBetween('created_at', [$from, $to]);

        $total     = (clone $base)->count();
        $byStatus  = (clone $base)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $byType    = (clone $base)->selectRaw('absence_type_id, count(*) as count, sum(days_count) as days')
                        ->with('absenceType:id,name')
                        ->groupBy('absence_type_id')
                        ->get();
        $byDept    = (clone $base)->join('users', 'users.id', '=', 'absence_requests.user_id')
                        ->join('departments', 'departments.id', '=', 'users.department_id')
                        ->selectRaw('departments.name as department, count(*) as count, sum(days_count) as days')
                        ->groupBy('departments.name')
                        ->get();
        $monthly   = (clone $base)->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as month, count(*) as count")
                        ->groupBy('month')
                        ->orderBy('month')
                        ->pluck('count', 'month');

        return response()->json([
            'total'     => $total,
            'by_status' => $byStatus,
            'by_type'   => $byType,
            'by_dept'   => $byDept,
            'monthly'   => $monthly,
        ]);
    }

    /**
     * GET /rh/reports/export
     * Export requests as CSV.
     */
    public function exportReport(Request $request)
    {
        $from   = $request->from   ?? now()->startOfYear()->toDateString();
        $to     = $request->to     ?? now()->toDateString();
        $deptId = $request->department_id;
        $typeId = $request->absence_type_id;
        $status = $request->status;

        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals.approver'])
            ->whereBetween('created_at', [$from, $to])
            ->when($deptId, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $deptId)))
            ->when($typeId, fn($q) => $q->where('absence_type_id', $typeId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport_absences_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($requests) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['ID', 'Employé', 'Email', 'Département', 'Type', 'Début', 'Fin', 'Jours', 'Raison', 'Statut', 'Créé le', 'Chef (date)', 'RH (date)', 'Directeur (date)']);

            foreach ($requests as $r) {
                $getApproval = fn(int $level) => $r->approvals->firstWhere('level', $level);
                $fmt = fn($approval) => $approval ? $approval->reviewed_at?->format('d/m/Y H:i') ?? '' : '';

                fputcsv($handle, [
                    $r->id,
                    $r->user->name,
                    $r->user->email,
                    $r->user->department?->name ?? '',
                    $r->absenceType->name,
                    $r->start_date->format('d/m/Y'),
                    $r->end_date->format('d/m/Y'),
                    $r->days_count,
                    $r->reason ?? '',
                    $r->status,
                    $r->created_at->format('d/m/Y H:i'),
                    $fmt($getApproval(1)),
                    $fmt($getApproval(2)),
                    $fmt($getApproval(3)),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
