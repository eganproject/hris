<?php

namespace App\Enums;

enum LeaveRequestStatus: string
{
    case PendingSupervisor = 'pending_supervisor'; // menunggu atasan
    case PendingHr = 'pending_hr';                 // menunggu HR
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingSupervisor => 'Menunggu Atasan',
            self::PendingHr => 'Menunggu HR',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingSupervisor, self::PendingHr => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PendingSupervisor, self::PendingHr], true);
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
