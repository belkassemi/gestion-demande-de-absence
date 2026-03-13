<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with('user')
            ->when($request->user_id,     fn($q) => $q->where('user_id',     $request->user_id))
            ->when($request->entity_type, fn($q) => $q->where('entity_type', $request->entity_type))
            ->when($request->entity_id,   fn($q) => $q->where('entity_id',   $request->entity_id))
            ->orderByDesc('timestamp')
            ->get();

        return response()->json($logs);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json($auditLog->load('user'));
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string',
            'entity_id'   => 'required|integer',
        ]);

        $history = AuditLog::getHistory($request->entity_type, $request->entity_id);

        return response()->json($history);
    }
}
