<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'branch_id',
    'user_id',
    'department_id',
    'job_position_id',
    'manager_id',
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
    ];

    /**
     * Employment status is binary: a person either works here (Aktif) or has left
     * (Nonaktif). Choosing Nonaktif on the form runs the exit flow (reason + date).
     */
    public const EMPLOYMENT_STATUS_LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
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

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function deviceMappings(): HasMany
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function swapRequests(): HasMany
    {
        return $this->hasMany(ShiftSwapRequest::class, 'requester_id');
    }

    public function swapRequestsAsPartner(): HasMany
    {
        return $this->hasMany(ShiftSwapRequest::class, 'partner_id');
    }

    /**
     * The employee's fingerprint-machine PIN (the global mapping, i.e. one that
     * applies to any device). Managed from the employee form for convenience.
     */
    protected function machineUserId(): Attribute
    {
        return Attribute::get(
            fn () => $this->deviceMappings()->whereNull('device_id')->value('machine_user_id'),
        );
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmployeeEvent::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
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

    public function getHrStatusLabelAttribute(): string
    {
        if ($this->isInactive()) {
            return trim('Tidak Aktif - '.($this->exit_reason_label ?? 'Alasan belum diisi'));
        }

        $status = $this->employment_status_label;
        $contract = $this->currentContract;

        if (! $contract) {
            return "{$status} - Belum ada kontrak aktif";
        }

        if ($contract->end_date === null) {
            return "{$status} - {$contract->contract_type} tanpa batas waktu";
        }

        $remainingDays = $this->remaining_contract_days;

        if ($remainingDays !== null && $remainingDays < 0) {
            return "{$status} - Kontrak berakhir ".abs($remainingDays).' hari lalu';
        }

        if ($remainingDays !== null && $remainingDays <= 30) {
            return "{$status} - Kontrak habis {$remainingDays} hari lagi";
        }

        return "{$status} - {$contract->contract_type} sampai ".$contract->end_date->format('d M Y');
    }

    public function isInactive(): bool
    {
        return $this->employment_status === 'inactive';
    }

    /**
     * Append an entry to the employee's work-history timeline. The causer defaults
     * to the authenticated user (null for background/console actions).
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordEvent(string $type, ?string $description = null, ?\Carbon\CarbonInterface $occurredAt = null, array $properties = []): EmployeeEvent
    {
        return $this->events()->create([
            'type' => $type,
            'description' => $description,
            'occurred_at' => $occurredAt ?? now(),
            'causer_id' => \Illuminate\Support\Facades\Auth::id(),
            'properties' => $properties === [] ? null : $properties,
        ]);
    }

    /**
     * Mark the employee as exited (nonaktif) in one place: employment record, the
     * current contract, and the login account. Used by the manual resign flow, the
     * automatic "contract expired" deactivation, and the inline exit during edit.
     *
     * @param  bool  $syncContract  When false, the caller has already set the contract
     *                              state (e.g. the edit form), so we leave it untouched.
     */
    public function markAsExited(string $reason, \Carbon\CarbonInterface $exitDate, ?string $notes = null, bool $syncContract = true): void
    {
        $this->forceFill([
            'employment_status' => 'inactive',
            'exit_reason' => $reason,
            'exit_date' => $exitDate,
            'exit_notes' => $notes,
        ])->save();

        if ($syncContract && $this->currentContract) {
            $this->currentContract->forceFill([
                'end_date' => $exitDate,
                'status' => $reason === 'contract_ended' ? 'completed' : 'ended_early',
            ])->save();
        }

        if ($this->user) {
            $this->user->forceFill(['is_active' => false])->save();
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $this->user->id)->delete();
        }
    }

    /**
     * Bring an employee back to active. Exit details are cleared (the timeline keeps
     * the history) and the login account is re-enabled.
     */
    public function reactivate(): void
    {
        $this->forceFill([
            'employment_status' => 'active',
            'exit_reason' => null,
            'exit_date' => null,
            'exit_notes' => null,
        ])->save();

        if ($this->user) {
            $this->user->forceFill(['is_active' => true])->save();
        }
    }

    /**
     * Employment (person) status, decoupled from the contract. Answers the single
     * question "is this person still an employee?".
     */
    public function getKepegawaianStatusLabelAttribute(): string
    {
        return match ($this->employment_status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => $this->employment_status_label,
        };
    }

    public function getKepegawaianStatusToneAttribute(): string
    {
        return match ($this->employment_status) {
            'active' => 'success',
            'inactive' => 'danger',
            default => 'neutral',
        };
    }

    /**
     * The employee is still active, but their current contract period has already
     * lapsed and nobody has renewed it or processed the exit yet. This is the
     * situation that needs an explicit HR decision (renew vs. exit).
     */
    public function getContractNeedsAttentionAttribute(): bool
    {
        return ! $this->isInactive() && (bool) $this->currentContract?->is_lapsed;
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
        ];
    }
}
