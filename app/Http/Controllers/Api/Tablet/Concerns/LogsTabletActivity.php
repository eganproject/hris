<?php

namespace App\Http\Controllers\Api\Tablet\Concerns;

use App\Models\AttendanceTablet;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait LogsTabletActivity
{
    /**
     * Catat aktivitas dengan pelaku "Tablet <nama>". ActivityLogger mengambil
     * pengguna dari guard aktif, yang di API tablet berisi tablet (bukan User),
     * jadi guard dikembalikan ke web (kosong) sebelum mencatat.
     */
    protected function audit(AttendanceTablet $tablet, string $event, string $description, ?Model $subject = null): void
    {
        Auth::shouldUse('web');

        ActivityLogger::log('face-tablets', $event, $description, $subject ?? $tablet, actorName: 'Tablet '.$tablet->name);
    }
}
