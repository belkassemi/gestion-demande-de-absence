<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'director_id'];


    // Relationships

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Methods (from UML)


    /**
     * Get all users belonging to this department.
     */
    public function getUsers()
    {
        return $this->users()->get();
    }

    /**
     * Get the director (head) of this department.
     */
    public function getDirector(): ?User
    {
        return $this->director;
    }

    /**
     * Get absence statistics for the department.
     */
    public function getStatistics(): array
    {
        $users = $this->users()->pluck('id');

        return [
            'total_employees'     => $users->count(),
            'total_requests'      => AbsenceRequest::whereIn('user_id', $users)->count(),
            'approved_requests'   => AbsenceRequest::whereIn('user_id', $users)->where('status', 'approved')->count(),
            'pending_requests'    => AbsenceRequest::whereIn('user_id', $users)->where('status', 'pending')->count(),
            'rejected_requests'   => AbsenceRequest::whereIn('user_id', $users)->where('status', 'rejected')->count(),
            'total_absence_days'  => AbsenceRequest::whereIn('user_id', $users)->where('status', 'approved')->sum('days_count'),
        ];
    }
}
