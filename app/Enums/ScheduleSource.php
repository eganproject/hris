<?php

namespace App\Enums;

enum ScheduleSource: string
{
    case Generated = 'generated'; // dibuat otomatis oleh generator dari pola
    case Manual = 'manual';       // override manual, tidak boleh ditimpa generator

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Otomatis',
            self::Manual => 'Manual',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Generated => 'neutral',
            self::Manual => 'info',
        };
    }
}
