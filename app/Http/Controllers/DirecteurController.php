<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\AbsenceApprovedMail;

class DirecteurController extends Controller
{
    /**
     * GET /api/directeur/pending-requests
     *
     * Retourne les demandes approuvées par le chef de service (niveau 1)
     * qui attendent maintenant la décision finale du directeur (niveau 2).
     *
     * Middleware: auth:sanctum, role:directeur
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * Response 200:
     *   [
     *     {
     *       "id": 1,
     *       "user": { "name": "Employé", "department": {"name": "IT"} },
     *       "absence_type": { "name": "Congé annuel" },
     *       "start_date": "2026-04-01",
     *       "end_date": "2026-04-05",
     *       "days_count": 5,
     *       "status": "pending",
     *       "current_level": 2,
     *       "approvals": [
     *         { "level": 1, "approver_role": "chef_service", "status": "approved", "comment": "OK" },
     *         { "level": 2, "approver_role": "directeur",    "status": "pending",  "comment": null }
     *       ],
     *       "employee_total_days": 15
     *     }
     *   ]
     */
    public function pendingRequests(): JsonResponse
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
            $absenceRequest->approve(2, $validated['comment'] ?? '');
            AuditLog::log('directeur_approved', 'AbsenceRequest', $absenceRequest->id, Auth::id());
            
            // Send email to the requester
            try {
                Mail::to($absenceRequest->user->email)->send(new AbsenceApprovedMail($absenceRequest));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send approval email: " . $e->getMessage());
            }

            $message = 'Demande approuvée définitivement.';
        } else {
            if (empty($validated['comment'])) {
                return response()->json(['message' => 'Un commentaire est obligatoire pour le rejet.'], 422);
            }
            $absenceRequest->reject(2, $validated['comment']);
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

        $pendingForDirecteur = AbsenceRequest::where('status', 'pending')->where('current_level', 2)->count();
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

        $byDept = AbsenceRequest::query()
            ->join('users', 'users.id', '=', 'absence_requests.user_id')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            ->whereYear('absence_requests.created_at', $year)
            ->selectRaw('departments.name as department,
                count(*) as total,
                sum(case when absence_requests.status = "approved" then 1 else 0 end) as approved,
                sum(absence_requests.days_count) as total_days')
            ->groupBy('departments.name')
            ->get();

        $byType = AbsenceRequest::query()
            ->join('absence_types', 'absence_types.id', '=', 'absence_requests.absence_type_id')
            ->whereYear('absence_requests.created_at', $year)
            ->selectRaw('absence_types.name as type,
                count(*) as count,
                sum(absence_requests.days_count) as days')
            ->groupBy('absence_types.name')
            ->get();

        $monthly = AbsenceRequest::query()
            ->whereYear('absence_requests.created_at', $year)
            ->selectRaw("DATE_FORMAT(absence_requests.created_at,'%Y-%m') as month,
                count(*) as count,
                sum(absence_requests.days_count) as days")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $topEmployees = AbsenceRequest::query()
            ->where('absence_requests.status', 'approved')
            ->join('users', 'users.id', '=', 'absence_requests.user_id')
            ->whereYear('absence_requests.created_at', $year)
            ->selectRaw('users.name, sum(absence_requests.days_count) as total_days')
            ->groupBy('users.id', 'users.name')
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

    /**
     * GET /directeur/reports/export
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
            fputcsv($handle, ['ID', 'Employé', 'Email', 'Département', 'Type', 'Début', 'Fin', 'Jours', 'Raison', 'Statut', 'Créé le', 'Chef (date)', 'Directeur (date)']);

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
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * GET /api/directeur/calendar
     *
     * Returns all absence requests (pending + approved) across all departments
     * for calendar display. Each absence includes department info for color-coding.
     *
     * Query Params (optional):
     *   month (string) - format "YYYY-MM"
     */
    public function allDepartmentsCalendar(Request $request): JsonResponse
    {
        $query = AbsenceRequest::with(['user.department', 'absenceType'])
            ->whereIn('status', ['pending', 'approved']);

        if ($request->month) {
            [$year, $month] = explode('-', $request->month);
            $query->where(function ($q) use ($year, $month) {
                $q->whereYear('start_date', $year)->whereMonth('start_date', $month)
                  ->orWhereYear('end_date', $year)->whereMonth('end_date', $month);
            });
        }

        $absences = $query->get()->map(function ($r) {
            return [
                'id'           => $r->id,
                'user'         => [
                    'id'         => $r->user->id,
                    'name'       => $r->user->name,
                    'department' => [
                        'id'   => $r->user->department?->id,
                        'name' => $r->user->department?->name,
                    ],
                ],
                'absence_type' => [
                    'name'  => $r->absenceType?->name,
                    'color' => $r->absenceType?->color,
                ],
                'start_date'   => $r->start_date,
                'end_date'     => $r->end_date,
                'days_count'   => $r->days_count,
                'status'       => $r->status,
                'reason'       => $r->reason,
            ];
        });

        return response()->json($absences);
    }
}
