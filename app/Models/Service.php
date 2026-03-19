<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'department_id',
        'chef_service_id',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function chefService(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_service_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
