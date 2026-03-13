<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    // Relationships


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Methods (from UML)

    /**
     * Create an audit log entry.
     */
    public static function log(string $action, string $entityType, int $entityId, ?int $userId = null, ?string $details = null): AuditLog
    {
        return static::create([
            'user_id'     => $userId ?? auth()->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
            'timestamp'   => now(),
        ]);
    }

    /**
     * Get the user who performed the action.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Get the full history for a given entity.
     */
    public static function getHistory(string $entityType, int $entityId)
    {
        return static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('user')
            ->orderByDesc('timestamp')
            ->get();
    }
}
