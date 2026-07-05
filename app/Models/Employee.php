<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'branch_id',
    'user_id',
    'department_id',
    'job_position_id',
    'employee_number',
    'photo_path',
    'full_name',
    'email',
    'phone',
    'identity_number',
    'birth_date',
    'join_date',
    'employment_status',
    'exit_reason',
    'exit_date',
    'exit_notes',
    'address',
    'resigned_at',
    'resignation_reason',
])]
class Employee extends Model
{
    public const EMPLOYMENT_STATUS_LABELS = [
        'active' => 'Aktif',
        'probation' => 'Probation',
        'suspended' => 'Skorsing / Ditangguhkan',
        'inactive' => 'Tidak Aktif / Sudah Tidak Bekerja',
    ];

    public const EXIT_REASON_LABELS = [
        'resigned' => 'Mengundurkan Diri',
        'terminated' => 'PHK',
        'contract_ended' => 'Kontrak Berakhir',
        'retired' => 'Pensiun',
        'deceased' => 'Meninggal Dunia',
        'mutual_agreement' => 'Kesepakatan Bersama',
        'absent_without_notice' => 'Mangkir',
        'layoff' => 'Efisiensi / Restrukturisasi',
        'other' => 'Lainnya',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(EmployeeContract::class)
            ->where('status', 'active')
            ->latestOfMany('start_date');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('employment_status', 'active');
    }

    public function scopeByBranch(Builder $query, int|string|null $branchId): void
    {
        $query->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    public function scopeByDepartment(Builder $query, int|string|null $departmentId): void
    {
        $query->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId));
    }

    public function getContractTenureAttribute(): ?string
    {
        $contract = $this->currentContract;

        if (! $contract?->start_date) {
            return null;
        }

        return $contract->start_date->diffForHumans($contract->end_date ?? now(), true);
    }

    public function getRemainingContractDaysAttribute(): ?int
    {
        $endDate = $this->currentContract?->end_date;

        if (! $endDate) {
            return null;
        }

        return now()->startOfDay()->diffInDays($endDate, false);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function getEmploymentStatusLabelAttribute(): string
    {
        return self::employmentStatusLabels()[$this->employment_status] ?? str($this->employment_status)->headline()->toString();
    }

    public function getExitReasonLabelAttribute(): ?string
    {
        if (! $this->exit_reason) {
            return null;
        }

        return self::exitReasonLabels()[$this->exit_reason] ?? str($this->exit_reason)->headline()->toString();
    }

    public function isInactive(): bool
    {
        return $this->employment_status === 'inactive';
    }

    /**
     * @return array<string, string>
     */
    public static function employmentStatusLabels(): array
    {
        return self::EMPLOYMENT_STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public static function exitReasonLabels(): array
    {
        return self::EXIT_REASON_LABELS;
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'exit_date' => 'date',
            'resigned_at' => 'datetime',
        ];
    }
}
