<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceRequest extends Model
{
    protected $fillable = [
        'user_id',
        'absence_type_id',
        'start_date',
        'end_date',
        'days_count',
        'reason',
        'document_path',
        'status',
        'current_level',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'request_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'request_id');
    }

    // -------------------------------------------------------------------------
    // Methods (from UML)
    // -------------------------------------------------------------------------

    /**
     * Calculate the number of working days between start and end dates.
     */
    public function calculateDays(): int
    {
        $start   = Carbon::parse($this->start_date);
        $end     = Carbon::parse($this->end_date);
        $days    = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Submit the request (set status to pending).
     */
    public function submit(): bool
    {
        $this->days_count = $this->calculateDays();
        $this->status     = 'pending';
        return $this->save();
    }

    /**
     * Cancel the request.
     */
    public function cancel(): bool
    {
        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Update request data.
     */
    public function updateData(array $data): bool
    {
        return $this->update($data);
    }

    /**
     * Get the current approver for this request.
     */
    public function getCurrentApprover(): ?User
    {
        $approval = $this->approvals()
            ->where('level', $this->current_level)
            ->where('status', 'pending')
            ->with('approver')
            ->first();

        return $approval?->approver;
    }

    /**
     * Get all approvals for this request.
     */
    public function getApprovals()
    {
        return $this->approvals()->orderBy('level')->get();
    }

    /**
     * Get the user who created the request.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Get the absence type for this request.
     */
    public function getAbsenceType(): ?AbsenceType
    {
        return $this->absenceType;
    }

    /**
     * Move approval to the next level.
     */
    public function moveToNextLevel(): bool
    {
        $this->current_level += 1;
        return $this->save();
    }

    /**
     * Approve the request at the given level.
     */
    public function approve(int $level, string $comment = ''): bool
    {
        $approval = $this->approvals()->where('level', $level)->first();

        if ($approval) {
            $approval->update([
                'status'      => 'approved',
                'comment'     => $comment,
                'reviewed_at' => now(),
            ]);
        }

        // Check if there is a next level; if not, mark the whole request approved
        $nextApproval = $this->approvals()->where('level', $level + 1)->first();

        if ($nextApproval) {
            return $this->moveToNextLevel();
        }

        $this->status = 'approved';
        return $this->save();
    }

    /**
     * Reject the request at the given level.
     */
    public function reject(int $level, string $comment = ''): bool
    {
        $approval = $this->approvals()->where('level', $level)->first();

        if ($approval) {
            $approval->update([
                'status'      => 'rejected',
                'comment'     => $comment,
                'reviewed_at' => now(),
            ]);
        }

        $this->status = 'rejected';
        return $this->save();
    }
}
