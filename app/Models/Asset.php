<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Support\AssetNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Asset extends Model
{
    use SoftDeletes;

    /**
     * asset_code sengaja tidak ada di sini: kodenya dibangkitkan sistem sesaat
     * setelah insert (lihat booted() / AssetNumber) dan tidak pernah boleh datang
     * dari formulir atau impor.
     *
     * status ADA di sini, tapi penjaganya ada di AssetRequest: formulir master hanya
     * boleh memilih status manual (AssetStatus::MANUAL). Status "dipegang" dan
     * "dilepas" hanya lahir dari alur kerjanya sendiri.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'brand',
        'model',
        'serial_number',
        'specification',
        'owning_branch_id',
        'current_branch_id',
        'department_id',
        'status',
        'condition',
        'acquired_at',
        'acquisition_cost',
        'warranty_expires_at',
        'notes',
        'created_by',
    ];

    /**
     * Riwayat yang membuat aset tidak boleh dihapus permanen. Sekarang berisi
     * dokumen saja; penyerahan dan transaksi lifecycle menyusul pada tahap custody,
     * dan cukup ditambahkan ke daftar ini.
     *
     * @var list<string>
     */
    public const HISTORY_RELATIONS = ['documents', 'assignments', 'transactions'];

    /**
     * Kode memuat id, jadi ia ditulis tepat setelah barisnya ada. Berbeda dengan
     * nomor karyawan, kode aset tidak pernah disegarkan ulang: ia identitas permanen
     * yang menempel di fisik barangnya.
     */
    protected static function booted(): void
    {
        static::created(function (Asset $asset): void {
            $asset->syncAssetCode();
            $asset->ensureOwningDepartmentLinked();
        });

        // Kode aset ditulis sekali, lalu terkunci selamanya.
        //
        // Tidak masuk $fillable saja belum cukup: itu hanya menutup pengisian massal
        // dari formulir. Penetapan langsung ($asset->asset_code = '...') maupun
        // forceFill() tetap lolos, dan sebuah fitur baru beberapa bulan lagi bisa
        // melakukannya tanpa sadar. Kode ini tercetak dan tertempel di fisik
        // barangnya — begitu ia berubah di basis data, label di gudang berbohong dan
        // tidak ada cara mengetahuinya selain menghitung ulang seluruh aset.
        //
        // Karena itu percobaannya digagalkan dengan keras, bukan didiamkan: sebuah
        // perubahan yang dibuang tanpa suara akan terbaca sebagai "tersimpan" oleh
        // pemanggilnya. Penulisan pertama (null -> kode) tetap diizinkan.
        static::updating(function (Asset $asset): void {
            if (! $asset->isDirty('asset_code') || $asset->getOriginal('asset_code') === null) {
                return;
            }

            throw new LogicException(sprintf(
                'Kode aset %s tidak boleh diubah (percobaan mengubahnya menjadi %s). '
                .'Kode aset adalah identitas permanen; daftarkan aset baru bila memang barangnya berbeda.',
                (string) $asset->getOriginal('asset_code'),
                (string) $asset->asset_code,
            ));
        });

        static::updated(function (Asset $asset): void {
            if ($asset->wasChanged('department_id')) {
                $asset->ensureOwningDepartmentLinked();
            }
        });
    }

    public function syncAssetCode(): void
    {
        if ($this->asset_code !== null) {
            return;
        }

        // Diam-diam: ini nilai turunan, bukan suntingan yang perlu memicu observer
        // dan menambah satu baris "Mengubah" di jejak aktivitas untuk tiap aset baru.
        $this->forceFill(['asset_code' => AssetNumber::for($this)])->saveQuietly();
    }

    /**
     * Divisi pemilik utama selalu ikut hadir di himpunan divisi, supaya cakupan data
     * cukup membaca satu tempat. Pola yang sama dengan Employee.
     */
    public function ensureOwningDepartmentLinked(): void
    {
        if ($this->department_id) {
            $this->departments()->syncWithoutDetaching([$this->department_id]);
        }
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    /** Unit yang memiliki aset — tidak ikut berubah saat barangnya dipindah. */
    public function owningBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'owning_branch_id');
    }

    /** Tempat barangnya berada sekarang. */
    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Himpunan divisi pemilik (satu atau dua), termasuk divisi utama. */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    /** @return list<int> */
    public function departmentIds(): array
    {
        if ($this->relationLoaded('departments')) {
            return $this->departments->pluck('id')->all();
        }

        $ids = $this->departments()->pluck('departments.id')->all();

        return $ids !== [] ? $ids : array_values(array_filter([$this->department_id]));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Masa pegang yang masih berjalan. Sebuah aset hanya boleh punya satu — dijaga
     * oleh AssignAsset lewat penguncian baris di dalam transaksi.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AssetTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Tandai seluruh relasi riwayat dalam satu query, agar daftar bisa menyembunyikan "Hapus" per baris. */
    public function scopeWithHistoryFlags(Builder $query): void
    {
        $query->withExists(self::HISTORY_RELATIONS);
    }

    public function hasHistory(): bool
    {
        foreach (self::HISTORY_RELATIONS as $relation) {
            $flag = str($relation)->snake()->toString().'_exists';

            if (array_key_exists($flag, $this->attributes)) {
                if ($this->attributes[$flag]) {
                    return true;
                }

                continue; // sudah ditandai withHistoryFlags(): tak perlu query lagi
            }

            if ($this->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aset hanya boleh dihapus selagi ia belum menjadi apa-apa: belum punya berkas,
     * dan belum melewati masa pakainya. Yang sudah berjalan diakhiri lewat status
     * "Tidak Dipakai" lalu disposal — supaya nilai dan riwayatnya tidak lenyap dari
     * pembukuan hanya karena satu klik.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->hasHistory()
            && in_array($this->status, [AssetStatus::Draft, AssetStatus::Available], true);
    }

    /**
     * Batasi query ke aset yang boleh dilihat pengguna: berada di (atau dimiliki
     * oleh) salah satu lokasi kerjanya DAN salah satu divisinya — daftar kosong pada
     * satu sumbu berarti "semua" untuk sumbu itu. Memegang izin bypass melepas
     * seluruh pembatasan; tidak punya bypass maupun cakupan berarti tidak melihat
     * apa pun.
     */
    public function scopeVisibleTo(Builder $query, User $user, string $bypassPermission = User::SCOPE_BYPASS_ASSETS): void
    {
        if ($user->seesAllData($bypassPermission)) {
            return;
        }

        // "Batasi ke bawahan" untuk aset berarti "aset yang sedang dipegang bawahan
        // saya" — garis atasan MENGGANTIKAN cakupan lokasi/divisi, sama seperti pada
        // modul absensi. Aset yang menganggur di gudang bukan urusan seorang atasan;
        // yang ia perlu tahu adalah barang yang ada di tangan timnya.
        //
        // [0] agar yang tidak punya bawahan mendapat daftar kosong, bukan seluruh tabel.
        if ($user->isLimitedToSubordinates()) {
            $ids = $user->subordinateEmployeeIds() ?: [0];

            $query->whereHas(
                'assignments',
                fn (Builder $assignment) => $assignment->open()->whereIn('employee_id', $ids),
            );

            return;
        }

        $branchIds = $user->accessBranchIds();
        $departmentIds = $user->accessDepartmentIds();

        if ($branchIds === [] && $departmentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($branchIds !== []) {
            // Dua lokasi diperiksa, bukan satu: aset yang sedang dititipkan ke cabang
            // lain harus tetap terlihat oleh cabang yang memilikinya.
            $query->where(function (Builder $query) use ($branchIds): void {
                $query->whereIn('current_branch_id', $branchIds)
                    ->orWhereIn('owning_branch_id', $branchIds);
            });
        }

        if ($departmentIds !== []) {
            $query->where(function (Builder $query) use ($departmentIds): void {
                $query->whereIn('department_id', $departmentIds)
                    ->orWhereHas('departments', fn (Builder $q) => $q->whereIn('departments.id', $departmentIds));
            });
        }
    }

    public function isVisibleTo(User $user, string $bypassPermission = User::SCOPE_BYPASS_ASSETS): bool
    {
        if ($user->seesAllData($bypassPermission)) {
            return true;
        }

        if ($user->isLimitedToSubordinates()) {
            return $this->assignments()
                ->open()
                ->whereIn('employee_id', $user->subordinateEmployeeIds() ?: [0])
                ->exists();
        }

        $branchIds = $user->accessBranchIds();
        $departmentIds = $user->accessDepartmentIds();

        if ($branchIds === [] && $departmentIds === []) {
            return false;
        }

        $inBranch = $branchIds === []
            || in_array($this->current_branch_id, $branchIds, true)
            || in_array($this->owning_branch_id, $branchIds, true);

        $inDepartment = $departmentIds === []
            || array_intersect($departmentIds, $this->departmentIds()) !== [];

        return $inBranch && $inDepartment;
    }

    /**
     * Penyaringan daftar aset. Satu tempat, dipakai bersama oleh halaman daftar dan
     * ekspor — supaya berkas yang diunduh selalu berisi persis yang tampil di layar.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeMatchingFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['condition'] ?? null, fn (Builder $query, $condition) => $query->where('condition', $condition))
            ->when($filters['branch'] ?? null, function (Builder $query, $id): void {
                $query->where(function (Builder $query) use ($id): void {
                    $query->where('current_branch_id', $id)->orWhere('owning_branch_id', $id);
                });
            })
            ->when($filters['department'] ?? null, function (Builder $query, $id): void {
                $query->where(function (Builder $query) use ($id): void {
                    $query->where('department_id', $id)
                        ->orWhereHas('departments', fn (Builder $q) => $q->where('departments.id', $id));
                });
            })
            ->when(($filters['warranty'] ?? null) === 'expiring', function (Builder $query): void {
                $query->whereNotNull('warranty_expires_at')
                    ->whereBetween('warranty_expires_at', [today(), today()->addDays(30)]);
            })
            ->when(($filters['warranty'] ?? null) === 'expired', function (Builder $query): void {
                $query->whereNotNull('warranty_expires_at')->whereDate('warranty_expires_at', '<', today());
            });
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '-';
    }

    public function getStatusToneAttribute(): string
    {
        return $this->status?->tone() ?? 'neutral';
    }

    public function getConditionLabelAttribute(): string
    {
        return $this->condition?->label() ?? '-';
    }

    public function getConditionToneAttribute(): string
    {
        return $this->condition?->tone() ?? 'neutral';
    }

    /** Garansi yang akan habis dalam 30 hari ke depan — dipakai daftar dan dashboard. */
    public function getWarrantyIsExpiringAttribute(): bool
    {
        return $this->warranty_expires_at !== null
            && $this->warranty_expires_at->betweenIncluded(today(), today()->addDays(30));
    }

    public function getWarrantyIsExpiredAttribute(): bool
    {
        return $this->warranty_expires_at !== null && $this->warranty_expires_at->isBefore(today());
    }

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'acquired_at' => 'date',
            'warranty_expires_at' => 'date',
            'acquisition_cost' => 'decimal:2',
        ];
    }
}
