<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceType extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['name', 'requires_document', 'color'];

    protected function casts(): array
    {
        return [
            'requires_document' => 'boolean',
            'created_at'        => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }

    // -------------------------------------------------------------------------
    // Methods (from UML)
    // -------------------------------------------------------------------------

    /**
     * Get all absence types.
     */
    public static function getAll()
    {
        return static::all();
    }

    /**
     * Get an absence type by its ID.
     */
    public static function getById(int $id): ?AbsenceType
    {
        return static::find($id);
    }

    /**
     * Check whether this absence type requires a supporting document.
     */
    public function requiresDoc(): bool
    {
        return (bool) $this->requires_document;
    }
}
