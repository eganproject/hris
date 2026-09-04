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
