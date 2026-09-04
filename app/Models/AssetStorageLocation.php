<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tempat penyimpanan aset di dalam satu lokasi kerja, tersusun bertingkat:
 * "Lantai 4 › Gudang A › Rak B", atau cukup "Lantai 2 › Ruang Office A".
 */
class AssetStorageLocation extends Model
{
    /**
     * full_path dan depth tidak ada di sini: keduanya nilai turunan yang dihitung
     * dari induk + nama setiap kali baris ini disimpan.
     *
     * @var list<string>
     */
    protected $fillable = ['branch_id', 'parent_id', 'code', 'name', 'is_active'];

    /** Pemisah antarjenjang pada full_path. */
    public const SEPARATOR = ' › ';

    /**
     * Banyak jenjang yang diizinkan (Lantai › Gudang › Lorong › Rak).
     *
     * Ada batasnya bukan karena basis datanya keberatan, melainkan karena pemilih di
     * formulir dan label di daftar aset menjadi tidak terbaca jauh sebelum itu.
     */
    public const MAX_DEPTH = 4;

    protected static function booted(): void
    {
        // Dihitung saat disimpan, bukan saat dibaca: daftar aset menampilkan jalur
        // ini untuk tiap barisnya, dan menelusuri pohon per baris akan menjadi
        // rentetan query yang tidak perlu.
        static::saving(function (self $location): void {
            $location->applyPathAttributes();
        });

        // Mengganti nama "Gudang A" harus ikut memperbarui seluruh rak di dalamnya —
        // kalau tidak, jalur anaknya menyebut nama yang sudah tidak ada.
        static::updated(function (self $location): void {
            if ($location->wasChanged('full_path')) {
                $location->refreshDescendantPaths();
            }
        });
    }

    public function applyPathAttributes(): void
    {
        $parent = $this->parent_id ? self::query()->find($this->parent_id) : null;
        $name = trim((string) $this->name);

        $this->full_path = $parent ? $parent->full_path.self::SEPARATOR.$name : $name;
        $this->depth = $parent ? (int) $parent->depth + 1 : 0;
    }

    /** Tulis ulang jalur seluruh keturunan, berjenjang ke bawah. */
    public function refreshDescendantPaths(): void
    {
        foreach ($this->children()->get() as $child) {
            $child->applyPathAttributes();
            // Diam-diam: ini pembaruan turunan, bukan suntingan yang perlu memenuhi
            // jejak aktivitas dengan satu baris per rak setiap kali gudangnya
            // diganti nama.
            $child->saveQuietly();
            $child->refreshDescendantPaths();
        }
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'storage_location_id');
    }

    /** Id baris ini beserta seluruh keturunannya — dipakai penjaga daur (cycle). */
    public function subtreeIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children()->get() as $child) {
            $ids = [...$ids, ...$child->subtreeIds()];
        }

        return $ids;
    }

    /**
     * Berapa jenjang lagi yang menggantung di bawah baris ini (0 bila tidak punya
     * anak). Dipakai saat memindahkan cabang pohon: yang harus muat bukan hanya
     * barisnya sendiri, tetapi seluruh keturunannya.
     */
    public function subtreeHeight(): int
    {
        $height = 0;

        foreach ($this->children()->get() as $child) {
            $height = max($height, 1 + $child->subtreeHeight());
        }

        return $height;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Urut menurut jalur: hasilnya sudah berbentuk pohon tanpa perlu disusun ulang. */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('branch_id')->orderBy('full_path');
    }

    /** Label lengkap dengan nama lokasi kerjanya — dipakai saat cabangnya belum jelas dari konteks. */
    public function getQualifiedPathAttribute(): string
    {
        return trim(($this->branch?->name ? $this->branch->name.self::SEPARATOR : '').$this->full_path);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'depth' => 'integer',
        ];
    }
}
