<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal mingguan yang diterbitkan atasan untuk seorang karyawan, dan kapan
 * karyawannya mengonfirmasi sudah melihatnya.
 */
class ScheduleAcknowledgement extends Model
{
    /** @var list<string> */
    protected $fillable = ['employee_id', 'week_start', 'published_by', 'published_at', 'confirmed_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    protected function weekStart(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
