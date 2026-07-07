<?php

namespace Database\Seeders;

use App\Enums\SchedulePatternType;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\SchedulePattern;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class AttendanceFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave', 'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12],
            ['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'counts_against_balance' => false, 'default_quota_days' => null],
            ['code' => 'SK', 'name' => 'Sakit', 'attendance_status' => 'sick', 'is_paid' => true, 'counts_against_balance' => false, 'default_quota_days' => null],
            ['code' => 'DL', 'name' => 'Dinas Luar', 'attendance_status' => 'business_trip', 'is_paid' => true, 'counts_against_balance' => false, 'default_quota_days' => null],
            ['code' => 'WFH', 'name' => 'Work From Home', 'attendance_status' => 'wfh', 'is_paid' => true, 'counts_against_balance' => false, 'default_quota_days' => null],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::query()->updateOrCreate(['code' => $type['code']], [...$type, 'is_active' => true]);
        }

        // A few well-known national holidays as starter data; the rest is managed via UI.
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru Masehi'],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional'],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan RI'],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::query()->updateOrCreate(
                ['date' => $holiday['date'], 'branch_id' => null],
                [...$holiday, 'is_national' => true],
            );
        }

        $this->seedSchedulePatterns();
    }

    /**
     * Starter shifts + two ready-to-use schedule patterns: a fixed Mon-Fri office
     * pattern and a 4-day rotating shift (pagi, siang, malam, libur).
     */
    private function seedSchedulePatterns(): void
    {
        $shifts = [
            'REG' => ['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60],
            'PG' => ['name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'break_minutes' => 60],
            'SG' => ['name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'break_minutes' => 60],
            'ML' => ['name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'break_minutes' => 60],
        ];

        $shiftIds = [];

        foreach ($shifts as $code => $attributes) {
            $shift = Shift::query()->updateOrCreate(
                ['code' => $code],
                [...$attributes, 'crosses_midnight' => $attributes['end_time'] <= $attributes['start_time'], 'is_active' => true],
            );
            $shiftIds[$code] = $shift->id;
        }

        // Fixed weekly: Reguler on Mon-Fri (dayOfWeek 1-5), Sat/Sun off (6, 0).
        $office = SchedulePattern::query()->updateOrCreate(
            ['code' => 'OFFICE-5D'],
            ['name' => 'Kantor Senin–Jumat', 'type' => SchedulePatternType::FixedWeekly, 'cycle_length' => 7, 'is_active' => true],
        );
        $office->days()->delete();
        foreach ([0 => null, 1 => 'REG', 2 => 'REG', 3 => 'REG', 4 => 'REG', 5 => 'REG', 6 => null] as $index => $code) {
            $office->days()->create(['day_index' => $index, 'shift_id' => $code ? $shiftIds[$code] : null]);
        }

        // Rotating: 4-day cycle pagi → siang → malam → libur.
        $rotation = SchedulePattern::query()->updateOrCreate(
            ['code' => 'ROT-3S'],
            ['name' => 'Rotasi 3 Shift (4 hari)', 'type' => SchedulePatternType::Rotating, 'cycle_length' => 4, 'anchor_date' => '2026-01-01', 'is_active' => true],
        );
        $rotation->days()->delete();
        foreach ([0 => 'PG', 1 => 'SG', 2 => 'ML', 3 => null] as $index => $code) {
            $rotation->days()->create(['day_index' => $index, 'shift_id' => $code ? $shiftIds[$code] : null]);
        }
    }
}
