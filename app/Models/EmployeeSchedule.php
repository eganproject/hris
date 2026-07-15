<?php

namespace App\Models;

use App\Enums\ScheduleSource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeSchedule extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'work_date',
    'shift_id',
    'is_day_off',
    'is_wfh',
    'source',
    'schedule_assignment_id',
    'note',
    ];

    protected function casts(): array
    {
        return [
            'is_day_off' => 'boolean',
            'is_wfh' => 'boolean',
            'source' => ScheduleSource::class,
        ];
    }

    /**
     * Store work_date as a pure Y-m-d string (not a datetime) so exact-match
     * lookups and the (employee_id, work_date) unique key behave predictably,
     * while still exposing a Carbon instance to the app.
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

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class, 'schedule_assignment_id');
    }

    public function isManual(): bool
    {
        return $this->source === ScheduleSource::Manual;
    }
}
