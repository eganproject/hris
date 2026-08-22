<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'leave_type_id',
    'supervisor_id',
    'start_date',
    'end_date',
    'reason',
    'attachment_path',
    'attachment_name',
    'attachment_mime',
    'attachment_size',
    'status',
    'supervisor_approved_by',
    'supervisor_decided_at',
    'approved_by',
    'decided_at',
    'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LeaveRequestStatus::class,
            'supervisor_decided_at' => 'datetime',
            'decided_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    /** Disk privat: lampiran hanya boleh keluar lewat rute berotorisasi. */
    public const ATTACHMENT_DISK = 'local';

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /** Gambar bisa dipratinjau langsung di tab baru; PDF diserahkan ke viewer browser. */
    public function attachmentIsImage(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'image/');
    }

    public function attachmentSizeLabel(): string
    {
        $bytes = (int) $this->attachment_size;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
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
