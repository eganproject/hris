<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu masa pegang: sejak aset diserahkan sampai dikembalikan.
 *
 * Barisnya tidak pernah ditimpa. Pengembalian mengisi returned_at beserta kondisi
 * saat kembali, bukan menyunting ulang penyerahannya — sehingga pertanyaan "siapa
 * memegang laptop ini bulan Maret lalu" selalu punya jawaban.
 */
class AssetAssignment extends Model
{
    /**
     * Keadaan masa pegang yang bisa dipilih di halaman daftar.
     *
     * Kuncinya ikut ke URL, jadi ia bagian dari antarmuka — mengganti kunci akan
     * mematahkan tautan yang sudah dibagikan orang.
     */
    public const STATES = [
        'open' => 'Sedang dipegang',
        'unacknowledged' => 'Belum dikonfirmasi',
        'overdue' => 'Telat kembali',
        'closed' => 'Sudah kembali',
        'all' => 'Semua',
    ];

    public const DEFAULT_STATE = 'open';

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'employee_id',
        'assigned_by',
        'assigned_at',
        'expected_return_at',
        'condition_out',
        'purpose',
        'notes',
        'acknowledged_at',
        'acknowledgement_note',
        'acknowledgement_reminded_at',
        'return_reminded_at',
        'returned_at',
        'returned_to',
        'condition_in',
        'return_notes',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /** Masa pegang yang masih berjalan — aset masih ada di tangan karyawannya. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('returned_at');
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('returned_at');
    }

    /** Sudah diserahkan tapi belum diakui karyawannya. */
    public function scopeAwaitingAcknowledgement(Builder $query): void
    {
        $query->whereNull('returned_at')->whereNull('acknowledged_at');
    }

    public static function resolveState(?string $state): string
    {
        return array_key_exists((string) $state, self::STATES) ? (string) $state : self::DEFAULT_STATE;
    }

    /** Menyaring menurut keadaan masa pegangnya. 'all' sengaja tidak menyaring apa pun. */
    public function scopeInState(Builder $query, ?string $state): void
    {
        match (self::resolveState($state)) {
            'open' => $query->open(),
            'unacknowledged' => $query->awaitingAcknowledgement(),
            'overdue' => $query->open()
                ->whereNotNull('expected_return_at')
                ->whereDate('expected_return_at', '<', today()),
            'closed' => $query->closed(),
            default => null,
        };
    }

    /**
     * Penyaring daftar serah-terima.
     *
     * Yang menyangkut asetnya sendiri — kategori, lokasi, divisi — sengaja TIDAK ada
     * di sini. Itu urusan Asset::scopeMatchingFilters(), dan halaman ini meminjamnya
     * lewat subkueri asset_id yang sama dengan yang sudah membatasi cakupan. Menyalin
     * aturannya ke sini berarti suatu hari daftar aset dan daftar serah-terima akan
     * menjawab "divisi IT" dengan dua himpunan yang berbeda.
     *
     * Yang tersisa adalah yang memang milik masa pegangnya: siapa memegang, dan kapan
     * diserahkan.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeMatchingFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                // Dicari di dua sisi sekaligus: barangnya dan orangnya. Yang membuka
                // halaman ini sama seringnya bertanya "di mana laptop LPT-0012" dan
                // "Budi sedang pegang apa saja".
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('asset', fn (Builder $asset) => $asset
                        ->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%"))
                        ->orWhereHas('employee', fn (Builder $employee) => $employee
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%"));
                });
            })
            ->when($filters['employee'] ?? null, fn (Builder $query, $id) => $query->where('employee_id', $id))
            // Rentangnya menyaring tanggal penyerahan, bukan pengembalian: itu tanggal
            // yang selalu ada di tiap baris, sedangkan returned_at kosong justru pada
            // baris yang paling sering dicari orang.
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('assigned_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('assigned_at', '<=', $date));
    }

    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Lewat tanggal kembali yang dijanjikan dan belum juga dikembalikan. Aset tanpa
     * tanggal target (dipegang untuk seterusnya) tidak pernah dianggap telat.
     */
    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->expected_return_at !== null
            && $this->expected_return_at->isBefore(today());
    }

    public function getConditionOutLabelAttribute(): string
    {
        return $this->condition_out?->label() ?? '-';
    }

    public function getConditionInLabelAttribute(): ?string
    {
        return $this->condition_in?->label();
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'expected_return_at' => 'date',
            'acknowledged_at' => 'datetime',
            'acknowledgement_reminded_at' => 'datetime',
            'return_reminded_at' => 'datetime',
            'returned_at' => 'datetime',
            'condition_out' => AssetCondition::class,
            'condition_in' => AssetCondition::class,
        ];
    }
}
