<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChefServiceController extends Controller
{
    /**
     * GET /api/chef-service/pending-requests
     *
     * Retourne les demandes d'absence de l'équipe du chef de service
     * qui sont en attente d'approbation au niveau 1 (chef_service).
     *
     * Middleware: auth:sanctum, role:chef_service
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * Query params: aucun
     *
     * Response 200:
     *   [
     *     {
     *       "id": 1,
     *       "user": { "id": 3, "name": "Employé Standard", "department": {...} },
     *       "absence_type": { "id": 1, "name": "Congé annuel" },
     *       "start_date": "2026-04-01",
     *       "end_date": "2026-04-05",
     *       "days_count": 5,
     *       "reason": "...",
     *       "status": "pending",
     *       "current_level": 1,
     *       "approvals": [...],
     *       "employee_total_days": 15
     *     }
     *   ]
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $chef = Auth::user();

        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals'])
            ->whereHas('user', fn($q) => $q->where('chef_service_id', $chef->id))
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
     * GET /api/chef-service/requests/{id}
     *
     * Retourne les détails complets d'une demande spécifique avec
     * historique des approbations et documents joints.
     *
     * Middleware: auth:sanctum, role:chef_service
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * URL Params:
     *   id (integer) - ID de la demande d'absence
     *
     * Response 200:
     *   {
     *     "id": 1,
     *     "user": { "name": "...", "department": {...} },
     *     "absence_type": { "name": "Congé annuel" },
     *     "start_date": "2026-04-01",
     *     "end_date": "2026-04-05",
     *     "days_count": 5,
     *     "reason": "...",
     *     "status": "pending",
     *     "current_level": 1,
     *     "approvals": [
     *       { "level": 1, "approver_role": "chef_service", "status": "pending", "comment": null },
     *       { "level": 2, "approver_role": "directeur",    "status": "pending", "comment": null }
     *     ],
     *     "documents": [],
     *     "employee_total_days": 15
     *   }
     */
    public function showRequest(AbsenceRequest $absenceRequest): JsonResponse
    {
        $absenceRequest->load(['user.department', 'absenceType', 'approvals.approver', 'documents']);
        $absenceRequest->employee_total_days = $absenceRequest->user->getTotalAbsenceDays();

        return response()->json($absenceRequest);
    }

    /**
     * POST /api/chef-service/requests/{id}/review
     *
     * Approuve ou rejette une demande au niveau 1 (chef de service).
     * Si approuvée → current_level passe à 2, en attente du directeur.
     * Si rejetée  → status = rejected.
     *
     * Middleware: auth:sanctum, role:chef_service
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *   Content-Type: application/json
     *
     * URL Params:
     *   id (integer) - ID de la demande
     *
     * Request Body:
     *   {
     *     "action":  "approve" | "reject",  // obligatoire
     *     "comment": "string"               // obligatoire si action=reject
     *   }
     *
     * Response 200:
     *   {
     *     "message": "Demande approuvée au niveau 1.",
     *     "request": { "id": 1, "status": "pending", "current_level": 2, ... }
     *   }
     *
     * Response 422 (rejet sans commentaire):
     *   { "message": "Un commentaire est obligatoire pour le rejet." }
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
     * GET /api/chef-service/team-calendar
     *
     * Retourne les absences approuvées de l'équipe du chef,
     * filtrées optionnellement par mois/année pour un affichage calendrier.
     *
     * Middleware: auth:sanctum, role:chef_service
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * Query Params (optionnels):
     *   month (integer) - numéro du mois (1-12)
     *   year  (integer) - année ex: 2026
     *
     * Response 200:
     *   [
     *     {
     *       "id": 1,
     *       "user": { "id": 4, "name": "Employé Standard" },
     *       "absence_type": { "name": "Congé annuel", "color": "#10B981" },
     *       "start_date": "2026-04-01",
     *       "end_date": "2026-04-05",
     *       "days_count": 5
     *     }
     *   ]
     */
    public function teamCalendar(Request $request): JsonResponse
    {
        $chef = Auth::user();

        $absences = AbsenceRequest::with(['user', 'absenceType'])
            ->whereHas('user', fn($q) => $q->where('chef_service_id', $chef->id))
            ->whereIn('status', ['pending', 'approved'])
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
     * GET /api/chef-service/team-history
     *
     * Retourne l'historique de toutes les demandes de l'équipe (tous statuts),
     * paginé 20 par page.
     *
     * Middleware: auth:sanctum, role:chef_service
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * Query Params (optionnels):
     *   status           (string)  - pending | approved | rejected | cancelled
     *   absence_type_id  (integer) - filtrer par type d'absence
     *   user_id          (integer) - filtrer par employé
     *   page             (integer) - numéro de page (défaut: 1)
     *
     * Response 200 (paginé):
     *   {
     *     "current_page": 1,
     *     "data": [ { "id": 1, "user": {...}, "status": "approved", ... } ],
     *     "per_page": 20,
     *     "total": 45
     *   }
     */
    public function teamHistory(Request $request): JsonResponse
    {
        $chef = Auth::user();

        $requests = AbsenceRequest::with(['user', 'absenceType', 'approvals'])
            ->whereHas('user', fn($q) => $q->where('chef_service_id', $chef->id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->absence_type_id, fn($q) => $q->where('absence_type_id', $request->absence_type_id))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->latest()
            ->paginate(20);

        return response()->json($requests);
    }

    /**
     * GET /api/chef-service/reports/export
     * Exporte l'historique de l'équipe du chef en CSV.
     */
    public function exportReport(Request $request)
    {
        $chef = Auth::user();
        $status = $request->status;

        $requests = AbsenceRequest::with(['user.department', 'absenceType', 'approvals.approver'])
            ->whereHas('user', fn($q) => $q->where('chef_service_id', $chef->id))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport_equipe_' . now()->format('Y-m-d') . '.csv"',
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
