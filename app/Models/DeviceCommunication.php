<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommunication extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'event',
        'records_count',
        'payload',
        'payload_bytes',
        'ip',
    ];

    /**
     * Batas isi kiriman yang disimpan. Sebuah mesin yang baru pulih dari mati
     * berhari-hari bisa mengirim puluhan ribu baris sekaligus; menyimpannya utuh
     * membuat tabel ini tumbuh jauh lebih cepat daripada gunanya. Yang dipangkas
     * hanya ekornya — bagian awal justru yang dibaca orang saat menelusuri.
     */
    public const PAYLOAD_LIMIT_BYTES = 60000;

    /** Penanda di ujung isi yang dipangkas, supaya tidak terbaca sebagai kiriman utuh. */
    public const TRUNCATION_MARK = '… (dipangkas)';

    /**
     * Potong isi kiriman ke batas simpan, dan kembalikan bersama ukuran aslinya.
     *
     * @return array{payload: string|null, payload_bytes: int|null}
     */
    public static function payloadColumnsFor(?string $body): array
    {
        $body = trim((string) $body);

        if ($body === '') {
            return ['payload' => null, 'payload_bytes' => null];
        }

        $bytes = strlen($body);

        return [
            'payload' => $bytes > self::PAYLOAD_LIMIT_BYTES
                ? substr($body, 0, self::PAYLOAD_LIMIT_BYTES)."\n".self::TRUNCATION_MARK
                : $body,
            'payload_bytes' => $bytes,
        ];
    }

    /** Isinya lebih panjang daripada yang disimpan. */
    public function isTruncated(): bool
    {
        return $this->payload_bytes !== null && $this->payload_bytes > self::PAYLOAD_LIMIT_BYTES;
    }

    public function payloadSizeLabel(): ?string
    {
        $bytes = (int) $this->payload_bytes;

        if ($bytes <= 0) {
            return null;
        }

        return $bytes >= 1024 ? round($bytes / 1024, 1).' KB' : $bytes.' B';
    }

    protected function casts(): array
    {
        return [
            'records_count' => 'integer',
            'payload_bytes' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            'handshake' => 'Handshake',
            'attlog' => 'Kirim absensi',
            'poll' => 'Polling',
            'command' => 'Perintah',
            default => 'Data',
        };
    }

    public function getEventToneAttribute(): string
    {
        return match ($this->event) {
            'attlog' => 'success',
            'handshake' => 'info',
            'command' => 'warning',
            default => 'neutral',
        };
    }
}
