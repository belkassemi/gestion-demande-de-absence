<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'service_id',
        'chef_service_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function chefService(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_service_id');
    }

    /** Employees in this chef's team */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'chef_service_id');
    }

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'approver_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // -------------------------------------------------------------------------
    // Methods (from UML)
    // -------------------------------------------------------------------------

    /**
     * Create a new absence request for this user.
     */
    public function createRequest(array $data): AbsenceRequest
    {
        return $this->absenceRequests()->create($data);
    }

    /**
     * Get all absence requests for this user.
     */
    public function getRequests()
    {
        return $this->absenceRequests()->with(['absenceType', 'approvals'])->get();
    }

    /**
     * Approve a specific absence request.
     */
    public function approveRequest(int $requestId, string $comment = ''): bool
    {
        $request = AbsenceRequest::findOrFail($requestId);
        return $request->approve($this->current_level ?? 1, $comment);
    }

    /**
     * Reject a specific absence request.
     */
    public function rejectRequest(int $requestId, string $comment = ''): bool
    {
        $request = AbsenceRequest::findOrFail($requestId);
        return $request->reject($this->current_level ?? 1, $comment);
    }

    /**
     * Get the department this user belongs to.
     */
    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    /**
     * Get the chef service (manager) of this user.
     */
    public function getChefService(): ?User
    {
        return $this->chefService;
    }

    /**
     * Get the total approved absence days for this user.
     */
    public function getTotalAbsenceDays(): int
    {
        return (int) $this->absenceRequests()
            ->where('status', 'approved')
            ->sum('days_count');
    }
}
