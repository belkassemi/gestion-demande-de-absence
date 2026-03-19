<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * GET /api/admin/dashboard
     *
     * Global KPIs for the admin dashboard (total requests, etc.)
     *
     * Middleware: auth:sanctum, role:admin
     * Headers: Authorization: Bearer {token}, Accept: application/json
     *
     * Response 200:
     *   {
     *     "total_requests": 150,
     *     "pending": 5,
     *     "approved": 140,
     *     "rejected": 5,
     *     "total_users": 50
     *   }
     */
    public function dashboard(): JsonResponse
    {
        $totalRequests = AbsenceRequest::count();
        $pending       = AbsenceRequest::where('status', 'pending')->count();
        $approved      = AbsenceRequest::where('status', 'approved')->count();
        $rejected      = AbsenceRequest::where('status', 'rejected')->count();
        $totalUsers    = User::count();

        return response()->json([
            'total_requests' => $totalRequests,
            'pending'        => $pending,
            'approved'       => $approved,
            'rejected'       => $rejected,
            'total_users'    => $totalUsers,
        ]);
    }

    /**
     * GET /api/admin/statistics
     *
     * Statistiques globales par département, par type d'absence, etc.
     *
     * Query Params:
     *   year (integer) - année ciblée
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

        return response()->json([
            'year'          => $year,
            'by_department' => $byDept,
            'by_type'       => $byType,
            'monthly'       => $monthly,
        ]);
    }

    /**
     * GET /api/admin/all-requests
     *
     * Liste de toutes les demandes avec pagination et filtres.
     *
     * Query Params: status, department_id, page
     */
    public function allRequests(Request $request): JsonResponse
    {
        $requests = AbsenceRequest::with(['user.department', 'user.service', 'absenceType'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $request->department_id)))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($requests);
    }

    /**
     * GET /api/admin/all-users-stats
     *
     * Récupère la liste de tous les utilisateurs et leur total de jours d'absence.
     */
    public function allUsersStats(Request $request): JsonResponse
    {
        $users = User::with('department', 'service')
            ->get()
            ->map(function ($u) {
                return [
                    'id'            => $u->id,
                    'name'          => $u->name,
                    'email'         => $u->email,
                    'department'    => $u->department?->name,
                    'service'       => $u->service?->name,
                    'total_days'    => $u->getTotalAbsenceDays(),
                ];
            });

        return response()->json($users);
    }

    /**
     * GET /api/admin/reports/export
     *
     * Exporte toutes les demandes dans un fichier CSV.
     *
     * Query Params: from (date), to (date), department_id, status
     * Response: fichier rapport_global_absences_{date}.csv (téléchargement direct)
     */
    public function exportReport(Request $request)
    {
        $from   = $request->from   ?? now()->startOfYear()->toDateString();
        $to     = $request->to     ?? now()->toDateString();
        $deptId = $request->department_id;
        $status = $request->status;

        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals.approver'])
            ->whereBetween('created_at', [$from, $to])
            ->when($deptId, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $deptId)))
            ->when($status, fn($q) => $q->where('status', $status))
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport_global_absences_' . now()->format('Y-m-d') . '.csv"',
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
}
