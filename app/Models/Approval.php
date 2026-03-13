<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'request_id',
        'approver_id',
        'level',
        'approver_role',
        'status',
        'comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function request(): BelongsTo
    {
        return $this->belongsTo(AbsenceRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // -------------------------------------------------------------------------
    // Methods (from UML)
    // -------------------------------------------------------------------------

    /**
     * Approve this approval record.
     */
    public function approve(string $comment = ''): bool
    {
        $this->status      = 'approved';
        $this->comment     = $comment;
        $this->reviewed_at = now();
        return $this->save();
    }

    /**
     * Reject this approval record.
     */
    public function reject(string $comment = ''): bool
    {
        $this->status      = 'rejected';
        $this->comment     = $comment;
        $this->reviewed_at = now();
        return $this->save();
    }

    /**
     * Get the approver user.
     */
    public function getApprover(): ?User
    {
        return $this->approver;
    }

    /**
     * Get the associated absence request.
     */
    public function getRequest(): ?AbsenceRequest
    {
        return $this->request;
    }
}
