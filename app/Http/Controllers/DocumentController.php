<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $docs = Document::with('request')
            ->when($request->request_id, fn($q) => $q->where('request_id', $request->request_id))
            ->get();

        return response()->json($docs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'request_id' => 'required|exists:absence_requests,id',
            'file'       => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $document = Document::upload($request->file('file'), $request->request_id);

        AuditLog::log('document_uploaded', 'Document', $document->id);

        return response()->json($document->load('request'), 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document->load('request'));
    }

    public function download(Document $document): BinaryFileResponse
    {
        $path = $document->download();

        abort_if($path === null, 404, 'File not found.');

        return response()->download($path, $document->file_name);
    }

    public function destroy(Document $document): JsonResponse
    {
        AuditLog::log('document_deleted', 'Document', $document->id);
        $document->deleteFile();
        return response()->json(['message' => 'Document deleted.']);
    }
}
