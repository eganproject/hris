<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /** @var list<string> */
    protected $fillable = ['code', 'name', 'description', 'is_active'
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['is_primary', 'is_active'])
            ->withTimestamps();
    }

    public function activeBranches(): BelongsToMany
    {
        return $this->branches()->wherePivot('is_active', true);
    }

    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPosition::class)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeJobPositions(): BelongsToMany
    {
        return $this->jobPositions()->wherePivot('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
