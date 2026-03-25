<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Notifications\RequestStatusChanged;

class AbsenceRequestController extends Controller
{
    /**
     * GET /api/absence-requests
     *
     * Retourne les demandes d'absence selon le rôle de l'utilisateur:
     * - employee    → seulement ses propres demandes
     * - chef_service → toutes les demandes de son équipe
     * - directeur / admin → toutes les demandes
     *
     * Middleware: auth:sanctum
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * Query Params (optionnels):
     *   status          (string)  - pending | approved | rejected | cancelled
     *   absence_type_id (integer) - filtrer par type
     *   page            (integer) - numéro de page
     *
     * Response 200 (paginé):
     *   {
     *     "current_page": 1,
     *     "data": [ { "id":1, "status":"pending", "days_count":5, ... } ],
     *     "per_page": 20,
     *     "total": 12
     *   }
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = AbsenceRequest::with(['user', 'absenceType', 'approvals.approver'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->absence_type_id, fn($q) => $q->where('absence_type_id', $request->absence_type_id))
            ->latest();

        // Scope by role
        if (in_array($user->role, ['employee'])) {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'chef_service') {
            $query->whereHas('user', fn($q) => $q->where('chef_service_id', $user->id));
        }
        // rh, directeur, admin can see all

        return response()->json($query->paginate(20));
    }

    /**
     * GET /api/absence-requests/my-stats
     *
     * Retourne les statistiques de l'année courante de l'employé connecté.
     *
     * Middleware: auth:sanctum
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * Response 200:
     *   {
     *     "year": 2026,
     *     "total_days": 10,
     *     "pending": 1,
     *     "approved": 2,
     *     "rejected": 0,
     *     "by_type": { "1": { "days": 5, "count": 1 } },
     *     "monthly":  { "2026-03": 1 }
     *   }
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

    /**
     * POST /api/absence-requests
     *
     * Crée une nouvelle demande d'absence pour l'employé connecté.
     * Crée automatiquement 2 enregistrements d'approbation (niveau 1 + niveau 2).
     *
     * Middleware: auth:sanctum
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *   Content-Type: application/json
     *
     * Request Body (JSON):
     *   {
     *     "user_id":         1,           // integer, obligatoire
     *     "absence_type_id": 1,           // integer, obligatoire
     *     "start_date":      "2026-04-01", // date YYYY-MM-DD, obligatoire
     *     "end_date":        "2026-04-05", // date >= start_date, obligatoire
     *     "reason":          "Vacances",   // string, optionnel
     *     "document":        null          // fichier PDF/image max 5MB, optionnel
     *   }
     *
     * Response 201:
     *   {
     *     "id": 1,
     *     "user_id": 2,
     *     "absence_type_id": 1,
     *     "start_date": "2026-04-01",
     *     "end_date": "2026-04-05",
     *     "days_count": 5,
     *     "reason": "Vacances",
     *     "status": "pending",
     *     "current_level": 1
     *   }
     *
     * Response 422: erreurs de validation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'absence_type_id'  => 'required|exists:absence_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'nullable|string',
            'document'         => 'nullable|file|max:5120', // Max 5MB file
        ]);

        $data = $validated;
        $data['user_id'] = Auth::id(); // Always infer from authenticated user
        
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
        $absenceRequest->submit(); // sets status pending, current_level=1, calculates days

        // Create the 2 approval records per PRD v2.0
        // Level 1: chef_service
        \App\Models\Approval::create([
            'request_id'    => $absenceRequest->id,
            'approver_id'   => $absenceRequest->user->chef_service_id ?? $absenceRequest->user_id, // fallback if no chef
            'level'         => 1,
            'approver_role' => 'chef_service',
            'status'        => 'pending',
        ]);

        // Level 2: directeur (we just set a placeholder or find a directeur, but typically the UI or query resolves this)
        // For now, we create the record. Director can see level 2 approvals regardless of approver_id as long as they are a director.
        // We'll set approver_id to a default directeur if exists or just a placeholder if the system supports generic role approvals.
        // Or if the company has one director, we can find them.
        $directeur = \App\Models\User::where('role', 'directeur')->first();
        \App\Models\Approval::create([
            'request_id'    => $absenceRequest->id,
            'approver_id'   => $directeur ? $directeur->id : $absenceRequest->user_id,
            'level'         => 2,
            'approver_role' => 'directeur',
            'status'        => 'pending',
        ]);

        AuditLog::log('created', 'AbsenceRequest', $absenceRequest->id);

        // Notify the Chef de Service if applicable
        if (isset($absenceRequest->user->chef_service_id)) {
            $chef = \App\Models\User::find($absenceRequest->user->chef_service_id);
            if ($chef) {
                $chef->notify(new RequestStatusChanged($absenceRequest, 'new_request'));
            }
        }

        return response()->json($absenceRequest->load('user', 'absenceType'), 201);
    }

    /**
     * GET /api/absence-requests/{id}
     *
     * Affiche les détails complets d'une demande avec approbations et documents.
     *
     * Middleware: auth:sanctum
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *
     * URL Params:
     *   id (integer) - ID de la demande
     *
     * Response 200:
     *   {
     *     "id": 1, "status": "pending", "current_level": 1,
     *     "user": {...}, "absence_type": {...},
     *     "approvals": [
     *       { "level":1, "approver_role":"chef_service", "status":"pending" },
     *       { "level":2, "approver_role":"directeur",    "status":"pending" }
     *     ],
     *     "documents": []
     *   }
     */
    public function show(AbsenceRequest $absenceRequest): JsonResponse
    {
        return response()->json(
            $absenceRequest->load('user', 'absenceType', 'approvals.approver', 'documents')
        );
    }

    /**
     * PUT /api/absence-requests/{id}
     *
     * Modifie une demande existante. Seules les demandes en statut "pending" peuvent être modifiées.
     *
     * Middleware: auth:sanctum
     *
     * Headers:
     *   Authorization: Bearer {token}
     *   Accept: application/json
     *   Content-Type: application/json
     *
     * URL Params:
     *   id (integer) - ID de la demande
     *
     * Request Body (champs optionnels):
     *   {
     *     "start_date": "2026-04-02",
     *     "end_date":   "2026-04-06",
     *     "reason":     "Nouveau motif"
     *   }
     *
     * Response 200: demande mise à jour
     * Response 422: { "message": "Seules les demandes en attente peuvent être modifiées." }
     */
    public function update(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        if ($absenceRequest->status !== 'pending') {
            return response()->json(['message' => 'Seules les demandes en attente peuvent être modifiées.'], 422);
        }

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
        if ($absenceRequest->status !== 'pending') {
            return response()->json(['message' => 'Seules les demandes en attente peuvent être annulées.'], 422);
        }

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
