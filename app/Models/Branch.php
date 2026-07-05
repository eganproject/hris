<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'type', 'city', 'province', 'address', 'is_active'])]
class Branch extends Model
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->withPivot(['is_primary', 'is_active'])
            ->withTimestamps();
    }

    public function activeDepartments(): BelongsToMany
    {
        return $this->departments()->wherePivot('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
