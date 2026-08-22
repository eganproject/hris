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

    /**
     * Status yang dihitung sebagai "hadir" di laporan: orangnya bekerja hari itu,
     * entah dari kantor, dari rumah, atau di luar kota. Dipakai bersama oleh papan
     * harian, rekap kehadiran, dan ekspor log absensi supaya angkanya tidak pernah
     * berbeda antar laporan.
     *
     * @var list<self>
     */
    public const WORKED = [self::Present, self::Late, self::EarlyLeave, self::Wfh, self::BusinessTrip];

    /** @return list<string> */
    public static function workedValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::WORKED);
    }

    public function isWorked(): bool
    {
        return in_array($this, self::WORKED, true);
    }

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
     * Warna solid (hex) untuk penanda yang tidak bisa memakai kelas Tailwind —
     * penanda peta, ikon SVG. Diturunkan dari tone() supaya satu status tidak
     * pernah tampil hijau di lencana tetapi biru di peta.
     */
    public function color(): string
    {
        return match ($this->tone()) {
            'success' => '#059669',
            'warning' => '#d97706',
            'danger' => '#dc2626',
            'info' => '#2563eb',
            default => '#6b7280',
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
