<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'employee_id',
    'leave_type_id',
    'supervisor_id',
    'start_date',
    'end_date',
    'reason',
    'status',
    'supervisor_approved_by',
    'supervisor_decided_at',
    'approved_by',
    'decided_at',
    'decision_notes',
])]
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LeaveRequestStatus::class,
            'supervisor_decided_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /** HR-level approver (final decision). */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisorApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }

    /**
     * Approved leave overlapping a date — used by the attendance resolver.
     */
    public function scopeApprovedOn(Builder $query, string $date): void
    {
        $query->where('status', LeaveRequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Requests that still hold quota (approved or in-flight).
     */
    public function scopeHoldsQuota(Builder $query): void
    {
        $query->whereIn('status', [
            LeaveRequestStatus::PendingSupervisor->value,
            LeaveRequestStatus::PendingHr->value,
            LeaveRequestStatus::Approved->value,
        ]);
    }

    public function getDaysAttribute(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }
}
