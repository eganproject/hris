<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'asset_prefix',
        'requires_serial',
        'useful_life_months',
        'description',
        'is_active',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** "5 tahun" / "18 bulan" — umur ekonomis dalam kalimat yang enak dibaca. */
    public function getUsefulLifeLabelAttribute(): ?string
    {
        $months = (int) $this->useful_life_months;

        if ($months <= 0) {
            return null;
        }

        if ($months % 12 === 0) {
            return ($months / 12).' tahun';
        }

        return $months.' bulan';
    }

    protected function casts(): array
    {
        return [
            'requires_serial' => 'boolean',
            'is_active' => 'boolean',
            'useful_life_months' => 'integer',
        ];
    }
}
