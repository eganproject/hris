<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'device_id',
    'employee_id',
    'machine_user_id',
    'punched_at',
    'state',
    'verify_mode',
    'status',
    'dedup_hash',
    'raw',
    ];

    /**
     * Penanda masuk/pulang yang dipilih di mesin sebelum jari ditempelkan. Angkanya
     * mengikuti protokol ZKTeco: 0 kedatangan, 1 kepulangan. Nilai lain dianggap tidak
     * memberi keterangan apa-apa.
     */
    public const STATE_IN = 0;

    public const STATE_OUT = 1;

    public const STATUS_MATCHED = 'matched';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_IGNORED = 'ignored';

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'state' => 'integer',
            'verify_mode' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeUnmatched(Builder $query): void
    {
        $query->where('status', self::STATUS_UNMATCHED);
    }

    public function getVerifyLabelAttribute(): string
    {
        return self::verifyLabel($this->verify_mode);
    }

    /**
     * Cara verifikasi yang dilaporkan mesin. Dibuat statis supaya halaman detail log
     * komunikasi bisa memberi label pada baris kiriman yang BELUM (atau tidak) menjadi
     * punch — di sana tidak ada model yang bisa ditanyai.
     *
     * Kode yang tidak dikenal tetap menampilkan angkanya. "Lainnya" saja tidak
     * memberi tahu apa pun kepada orang yang sedang menelusuri kiriman mesin.
     */
    public static function verifyLabel(?int $mode): string
    {
        return match ($mode) {
            0 => 'Password',
            1 => 'Sidik jari',
            2 => 'Kartu',
            15 => 'Wajah',
            null => '—',
            default => "Lainnya ({$mode})",
        };
    }
}
