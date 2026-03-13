<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'uploaded_at';

    protected $fillable = [
        'request_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'file_size'   => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function request(): BelongsTo
    {
        return $this->belongsTo(AbsenceRequest::class);
    }

    // -------------------------------------------------------------------------
    // Methods (from UML)
    // -------------------------------------------------------------------------

    /**
     * Upload a file and create a Document record.
     */
    public static function upload(UploadedFile $file, int $requestId): Document
    {
        $path = $file->store('documents', 'public');

        return static::create([
            'request_id' => $requestId,
            'file_path'  => $path,
            'file_name'  => $file->getClientOriginalName(),
            'file_type'  => $file->getMimeType(),
            'file_size'  => $file->getSize(),
        ]);
    }

    /**
     * Download this document (returns the stored file path).
     */
    public function download(): ?string
    {
        return Storage::disk('public')->exists($this->file_path)
            ? Storage::disk('public')->path($this->file_path)
            : null;
    }

    /**
     * Delete this document and its file from storage.
     */
    public function deleteFile(): bool
    {
        Storage::disk('public')->delete($this->file_path);
        return $this->delete();
    }

    /**
     * Get the associated absence request.
     */
    public function getRequest(): ?AbsenceRequest
    {
        return $this->request;
    }
}
