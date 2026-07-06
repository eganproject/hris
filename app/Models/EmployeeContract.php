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

    /**
     * Contract statuses that mean the working relationship is being closed. Setting
     * the current contract to any of these during an edit triggers the exit flow.
     * ("renewed" is excluded: the employee continues under a renewed contract.)
     */
    public const CLOSING_STATUSES = ['completed', 'ended_early', 'expired', 'cancelled'];

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
     * A stored "active" contract whose end date has already passed without being
     * renewed or closed out. This is the state that used to silently keep showing
     * as "Aktif" even though the contract period was already over.
     */
    public function getIsLapsedAttribute(): bool
    {
        return $this->status === 'active'
            && $this->end_date !== null
            && $this->remaining_days !== null
            && $this->remaining_days < 0;
    }

    /**
     * The status that should actually be shown to a human, taking the calendar
     * into account. For non-active stored statuses we keep the stored label; for
     * active contracts we derive open-ended / dated / expiring / lapsed.
     */
    public function getEffectiveStatusLabelAttribute(): string
    {
        if ($this->status !== 'active') {
            return $this->status_label;
        }

        if ($this->end_date === null) {
            return 'Aktif · tanpa batas waktu';
        }

        $remaining = $this->remaining_days;

        if ($remaining < 0) {
            return 'Kedaluwarsa · berakhir '.abs($remaining).' hari lalu (belum diperbarui)';
        }

        if ($remaining === 0) {
            return 'Berakhir hari ini';
        }

        if ($remaining <= 30) {
            return 'Akan berakhir · '.$remaining.' hari lagi';
        }

        return 'Aktif · s/d '.$this->end_date->format('d M Y');
    }

    public function getEffectiveStatusToneAttribute(): string
    {
        if ($this->status === 'active') {
            if ($this->end_date === null) {
                return 'success';
            }

            $remaining = $this->remaining_days;

            return match (true) {
                $remaining < 0 => 'danger',
                $remaining <= 30 => 'warning',
                default => 'success',
            };
        }

        return match ($this->status) {
            'renewed', 'completed' => 'info',
            'expired' => 'danger',
            'ended_early', 'cancelled' => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * @return array<int, string>
     */
    public static function closingStatuses(): array
    {
        return self::CLOSING_STATUSES;
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
