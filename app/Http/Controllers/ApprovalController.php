<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $approvals = Approval::with(['request', 'approver'])
            ->when($request->approver_id, fn($q) => $q->where('approver_id', $request->approver_id))
            ->when($request->request_id,  fn($q) => $q->where('request_id',  $request->request_id))
            ->get();

        return response()->json($approvals);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id'    => 'required|exists:absence_requests,id',
            'approver_id'   => 'required|exists:users,id',
            'level'         => 'required|integer|min:1',
            'approver_role' => 'required|string|max:100',
        ]);

        $approval = Approval::create($validated);
        return response()->json($approval->load('approver', 'request'), 201);
    }

    public function show(Approval $approval): JsonResponse
    {
        return response()->json($approval->load('approver', 'request.user'));
    }

    public function approve(Request $request, Approval $approval): JsonResponse
    {
        $comment = $request->validate(['comment' => 'nullable|string'])['comment'] ?? '';
        $approval->approve($comment);

        AuditLog::log('approval_approved', 'Approval', $approval->id);

        return response()->json(['message' => 'Approved.', 'approval' => $approval->fresh()]);
    }

    public function reject(Request $request, Approval $approval): JsonResponse
    {
        $comment = $request->validate(['comment' => 'nullable|string'])['comment'] ?? '';
        $approval->reject($comment);

        AuditLog::log('approval_rejected', 'Approval', $approval->id);

        return response()->json(['message' => 'Rejected.', 'approval' => $approval->fresh()]);
    }
}
