<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'code',
    'name',
    'attendance_status',
    'is_paid',
    'counts_against_balance',
    'default_quota_days',
    'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'is_paid' => 'boolean',
            'counts_against_balance' => 'boolean',
            'default_quota_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
