<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DirecteurController extends Controller
{
    /**
     * GET /directeur/pending-requests
     * Returns requests at level 3 (Chef + RH both approved).
     */
    public function pendingRequests(): JsonResponse
    {
        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals.approver', 'documents'])
            ->where('status', 'pending')
            ->where('current_level', 3)
            ->orderBy('created_at')
            ->get()
            ->map(function ($r) {
                $r->employee_total_days = $r->user->getTotalAbsenceDays();
                return $r;
            });

        return response()->json($requests);
    }

    /**
     * GET /directeur/requests/{id}
     * Full details with complete approval history.
     */
    public function showRequest(AbsenceRequest $absenceRequest): JsonResponse
    {
        $absenceRequest->load(['user.department', 'absenceType', 'approvals.approver', 'documents']);
        $absenceRequest->employee_total_days = $absenceRequest->user->getTotalAbsenceDays();

        return response()->json($absenceRequest);
    }

    /**
     * POST /directeur/requests/{id}/review
     * Final approval or rejection (level 3).
     * Body: { action: 'approve'|'reject', comment?: string }
     */
    public function review(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'action'  => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'approve') {
            $absenceRequest->approve(3, $validated['comment'] ?? '');
            $absenceRequest->update(['current_level' => 4]);
            AuditLog::log('directeur_approved', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            $message = 'Demande approuvée définitivement.';
        } else {
            if (empty($validated['comment'])) {
                return response()->json(['message' => 'Un commentaire est obligatoire pour le rejet.'], 422);
            }
            $absenceRequest->reject(3, $validated['comment']);
            AuditLog::log('directeur_rejected', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            $message = 'Demande rejetée définitivement.';
        }

        return response()->json(['message' => $message, 'request' => $absenceRequest->fresh()]);
    }

    /**
     * GET /directeur/dashboard
     * Executive KPI dashboard.
     */
    public function dashboard(): JsonResponse
    {
        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd   = now()->subMonthNoOverflow()->endOfMonth();

        $pendingForDirecteur = AbsenceRequest::where('status', 'pending')->where('current_level', 3)->count();
        $approvedThisMonth   = AbsenceRequest::where('status', 'approved')->where('updated_at', '>=', $thisMonthStart)->count();
        $rejectedThisMonth   = AbsenceRequest::where('status', 'rejected')->where('updated_at', '>=', $thisMonthStart)->count();
        $approvedLastMonth   = AbsenceRequest::where('status', 'approved')->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd])->count();

        $totalDecided = $approvedThisMonth + $rejectedThisMonth;
        $approvalRate = $totalDecided > 0 ? round(($approvedThisMonth / $totalDecided) * 100, 1) : 0;

        // Monthly trend (last 6 months)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $trend[] = [
                'month'    => $month->format('M Y'),
                'approved' => AbsenceRequest::where('status', 'approved')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
                'rejected' => AbsenceRequest::where('status', 'rejected')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
            ];
        }

        return response()->json([
            'pending_for_directeur' => $pendingForDirecteur,
            'approved_this_month'   => $approvedThisMonth,
            'rejected_this_month'   => $rejectedThisMonth,
            'approved_last_month'   => $approvedLastMonth,
            'approval_rate'         => $approvalRate,
            'trend'                 => $trend,
        ]);
    }

    /**
     * GET /directeur/statistics
     * Global statistics for strategic view.
     */
    public function statistics(Request $request): JsonResponse
    {
        $year = $request->year ?? now()->year;

        $base = AbsenceRequest::whereYear('created_at', $year);

        $byDept = (clone $base)
            ->join('users', 'users.id', '=', 'absence_requests.user_id')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            ->selectRaw('departments.name as department, count(*) as total, sum(case when absence_requests.status = "approved" then 1 else 0 end) as approved, sum(days_count) as total_days')
            ->groupBy('departments.name')
            ->get();

        $byType = (clone $base)
            ->join('absence_types', 'absence_types.id', '=', 'absence_requests.absence_type_id')
            ->selectRaw('absence_types.name as type, count(*) as count, sum(days_count) as days')
            ->groupBy('absence_types.name')
            ->get();

        $monthly = (clone $base)
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as month, count(*) as count, sum(days_count) as days")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $topEmployees = (clone $base)
            ->where('status', 'approved')
            ->join('users', 'users.id', '=', 'absence_requests.user_id')
            ->selectRaw('users.name, sum(days_count) as total_days')
            ->groupBy('users.name')
            ->orderByDesc('total_days')
            ->limit(10)
            ->get();

        return response()->json([
            'year'          => $year,
            'by_department' => $byDept,
            'by_type'       => $byType,
            'monthly'       => $monthly,
            'top_employees' => $topEmployees,
        ]);
    }
}
