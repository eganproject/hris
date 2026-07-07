<?php

namespace App\Enums;

enum SchedulePatternType: string
{
    case FixedWeekly = 'fixed_weekly'; // pola tetap per hari dalam seminggu
    case Rotating = 'rotating';        // rotasi siklus N hari dari tanggal jangkar

    public function label(): string
    {
        return match ($this) {
            self::FixedWeekly => 'Mingguan Tetap',
            self::Rotating => 'Rotasi',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FixedWeekly => 'Shift ditentukan per hari (Senin–Minggu) dan berulang tiap pekan.',
            self::Rotating => 'Siklus shift berulang setiap sejumlah hari, tanpa terikat hari dalam seminggu.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
