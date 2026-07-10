<?php

namespace Database\Seeders;

use App\Enums\SchedulePatternType;
use App\Models\SchedulePattern;
use App\Models\SchedulePatternDay;
use App\Models\Shift;
use Illuminate\Database\Seeder;

/**
 * One-time setup for office-hours employees: an 08:00–17:00 shift and a fixed
 * weekly pattern that works Monday–Saturday with only Sunday off. Assign employees
 * to this pattern once (Jadwal Kerja → Assign) and the rolling roster generator
 * keeps them scheduled forever; national holidays override automatically.
 *
 * Idempotent — safe to re-run. Run with:
 *   php artisan db:seed --class=OfficeSchedulePatternSeeder
 */
class OfficeSchedulePatternSeeder extends Seeder
{
    public function run(): void
    {
        $shift = Shift::query()->updateOrCreate(
            ['code' => 'OFFICE'],
            [
                'name' => 'Jam Kantor',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'crosses_midnight' => false,
                'break_minutes' => 60,
                'late_tolerance_minutes' => 10,
                'early_leave_tolerance_minutes' => 10,
                'overtime_starts_after_minutes' => 30,
                'overtime_min_minutes' => 30,
                'is_active' => true,
            ],
        );

        $pattern = SchedulePattern::query()->updateOrCreate(
            ['code' => 'OFFICE'],
            [
                'name' => 'Jam Kantor (Senin–Sabtu)',
                'type' => SchedulePatternType::FixedWeekly,
                'cycle_length' => 7,
                'anchor_date' => null,
                'is_active' => true,
            ],
        );

        // FixedWeekly slot = Carbon dayOfWeek (0=Minggu … 6=Sabtu).
        // Senin–Sabtu (1–6) masuk dengan shift Jam Kantor; Minggu (0) tanpa slot = libur.
        $pattern->days()->delete();

        foreach (range(1, 6) as $dayIndex) {
            SchedulePatternDay::query()->create([
                'schedule_pattern_id' => $pattern->id,
                'day_index' => $dayIndex,
                'shift_id' => $shift->id,
            ]);
        }
    }
}
