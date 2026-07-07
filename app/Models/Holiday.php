<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'date',
    'name',
    'is_national',
    'branch_id',
    'notes',
])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_national' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Holidays that apply to a branch: national ones plus that branch's own.
     */
    public function scopeAppliesTo(Builder $query, int|string|null $branchId): void
    {
        $query->where(function (Builder $query) use ($branchId): void {
            $query->where('is_national', true)
                ->orWhere('branch_id', $branchId);
        });
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->is_national ? 'Nasional' : ($this->branch?->name ?? 'Lokasi tertentu');
    }
}
