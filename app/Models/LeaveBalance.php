<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'leave_type_id',
    'year',
    'quota_days',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quota_days' => 'integer',
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
}
