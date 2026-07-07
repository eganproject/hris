<?php

namespace App\Enums;

/**
 * The resolved status of an employee on a single work date. This is the shared
 * vocabulary the attendance resolver, reports and UI will all use.
 */
enum AttendanceStatus: string
{
    case Present = 'present';        // Hadir tepat waktu
    case Late = 'late';              // Terlambat
    case EarlyLeave = 'early_leave'; // Pulang cepat
    case Absent = 'absent';          // Alfa / mangkir
    case Leave = 'leave';            // Cuti / izin (disetujui)
    case Sick = 'sick';              // Sakit
    case BusinessTrip = 'business_trip'; // Dinas luar
    case Wfh = 'wfh';                // Kerja dari rumah
    case Holiday = 'holiday';        // Hari libur
    case DayOff = 'day_off';         // Libur sesuai jadwal

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::EarlyLeave => 'Pulang Cepat',
            self::Absent => 'Alfa',
            self::Leave => 'Cuti / Izin',
            self::Sick => 'Sakit',
            self::BusinessTrip => 'Dinas Luar',
            self::Wfh => 'WFH',
            self::Holiday => 'Libur Nasional',
            self::DayOff => 'Libur',
        };
    }

    /**
     * Maps to the <x-status-badge> colour set.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Present, self::Wfh => 'success',
            self::Late, self::EarlyLeave => 'warning',
            self::Absent => 'danger',
            self::Leave, self::Sick, self::BusinessTrip => 'info',
            self::Holiday, self::DayOff => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
