<?php

namespace App\Models;

use App\Enums\AssetTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kejadian pada garis waktu aset. Ditulis sekali dan tidak pernah disunting —
 * karena itu tidak ada updated_at, dan tidak ada satu pun jalur di aplikasi yang
 * memperbaruinya.
 *
 * Nama asal/tujuan ikut disalin sebagai teks: riwayat harus tetap terbaca setelah
 * cabang ditutup atau karyawannya dihapus.
 */
class AssetTransaction extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'assignment_id',
        'actor_id',
        'actor_name',
        'type',
        'from_status',
        'to_status',
        'from_branch_id',
        'to_branch_id',
        'from_employee_id',
        'to_employee_id',
        'from_label',
        'to_label',
        'condition',
        'occurred_at',
        'notes',
        'metadata',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssetAssignment::class, 'assignment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type?->label() ?? '-';
    }

    public function getTypeToneAttribute(): string
    {
        return $this->type?->tone() ?? 'neutral';
    }

    protected function casts(): array
    {
        return [
            'type' => AssetTransactionType::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
