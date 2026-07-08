<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'work_date',
    'shift_id',
    'status',
    'clock_in',
    'clock_out',
    'late_minutes',
    'early_leave_minutes',
    'work_minutes',
    'overtime_minutes',
    'leave_request_id',
    'holiday_id',
    'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'work_minutes' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    /**
     * Store work_date as a pure Y-m-d string so the (employee_id, work_date) unique
     * key and exact-match lookups stay reliable. Mirrors EmployeeSchedule.
     */
    protected function workDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    public function getClockInLabelAttribute(): string
    {
        return $this->clock_in?->format('H:i') ?? '–';
    }

    public function getClockOutLabelAttribute(): string
    {
        return $this->clock_out?->format('H:i') ?? '–';
    }
}
