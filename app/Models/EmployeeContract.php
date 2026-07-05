<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'employee_id',
    'contract_number',
    'contract_type',
    'start_date',
    'end_date',
    'status',
    'notes',
])]
class EmployeeContract extends Model
{
    public const STATUS_LABELS = [
        'active' => 'Aktif',
        'completed' => 'Selesai Sesuai Masa Kontrak',
        'ended_early' => 'Diakhiri Lebih Awal',
        'renewed' => 'Diperpanjang',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa / Belum Diperbarui',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query
            ->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function getRemainingDaysAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->end_date, false);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? str($this->status)->headline()->toString();
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
