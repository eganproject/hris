<?php

namespace App\Support;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Register aset: seluruh aset yang boleh dilihat pengguna, disaring, diringkas per
 * satu sumbu pilihan, lalu didaftar satu per satu.
 *
 * Cakupan dan penyaringnya sengaja meminjam Asset::scopeVisibleTo() dan
 * scopeMatchingFilters() yang sama dengan halaman Daftar Aset — bukan salinannya.
 * Sebuah laporan yang menghitung dari aturan sendiri cepat atau lambat akan berbeda
 * angka dengan layar yang dilihat orang, dan tidak ada yang tahu mana yang benar.
 *
 * Dipakai bersama oleh halaman web, ekspor Excel, dan cetak PDF supaya ketiganya
 * tidak mungkin bercerita berbeda.
 */
class AssetRegisterReport
{
    /**
     * Sumbu pengelompokan rekap: kunci => label.
     *
     * Kuncinya ikut ke URL dan ke berkas ekspor, jadi ia bagian dari antarmuka —
     * mengganti kunci akan mematahkan tautan laporan yang sudah dibagikan orang.
     */
    public const GROUPS = [
        'category' => 'Kategori',
        'owning_branch' => 'Lokasi Pemilik',
        'current_branch' => 'Lokasi Sekarang',
        'department' => 'Divisi Pemilik',
        'status' => 'Status',
        'condition' => 'Kondisi',
    ];

    public const DEFAULT_GROUP = 'category';

    /** Kolom yang menyimpan tiap sumbu — dipakai untuk GROUP BY. */
    private const GROUP_COLUMNS = [
        'category' => 'category_id',
        'owning_branch' => 'owning_branch_id',
        'current_branch' => 'current_branch_id',
        'department' => 'department_id',
        'status' => 'status',
        'condition' => 'condition',
    ];

    public static function resolveGroup(?string $group): string
    {
        return array_key_exists((string) $group, self::GROUPS) ? (string) $group : self::DEFAULT_GROUP;
    }

    /**
     * Basis seluruh angka di laporan ini: cakupan pengguna + penyaring yang dipilih.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    public function base(DataScope $scope, array $filters): Builder
    {
        return $scope->assets()->matchingFilters($filters);
    }

    /**
     * Daftar asetnya sendiri, urut kode aset supaya dua kali cetak selalu sama.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    public function register(DataScope $scope, array $filters): Builder
    {
        return $this->base($scope, $filters)
            ->with([
                'category:id,name',
                'owningBranch:id,name',
                'currentBranch:id,name',
                'department:id,name',
                'currentAssignment.employee:id,full_name,employee_number',
            ])
            ->orderBy('asset_code');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, value: float, warranty_expiring: int, assigned: int, in_use: int}
     */
    public function summary(DataScope $scope, array $filters): array
    {
        $base = fn () => $this->base($scope, $filters);

        return [
            'total' => $base()->count(),
            'value' => (float) ($base()->sum('acquisition_cost') ?: 0),
            'warranty_expiring' => $base()
                ->whereNotNull('warranty_expires_at')
                ->whereBetween('warranty_expires_at', [today(), today()->addDays(30)])
                ->count(),
            'assigned' => $base()->where('status', AssetStatus::Assigned->value)->count(),
            // Terpisah dari 'assigned'. Keduanya sama-sama "sedang terpakai", tapi
            // hanya yang Assigned punya nama yang bisa dimintai pertanggungjawaban;
            // satu angka gabungan menghilangkan beda itu justru di laporan yang
            // dipakai untuk menagih.
            'in_use' => $base()->where('status', AssetStatus::InUse->value)->count(),
        ];
    }

    /**
     * Rekap per satu sumbu: berapa aset dan berapa nilainya di tiap kelompok.
     *
     * Dihitung dengan satu GROUP BY lalu namanya dicarikan setelahnya, bukan lewat
     * join — dengan begitu aturan cakupan di base() tetap satu-satunya yang membatasi
     * baris, dan tidak ada join yang diam-diam ikut menyaring atau menggandakan.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{key: int|string|null, label: string, count: int, value: float}>
     */
    public function groups(DataScope $scope, array $filters, string $groupBy): Collection
    {
        $groupBy = self::resolveGroup($groupBy);
        $column = self::GROUP_COLUMNS[$groupBy];

        $rows = $this->base($scope, $filters)
            ->reorder()
            ->groupBy($column)
            ->selectRaw("{$column} as group_key, count(*) as assets_count, coalesce(sum(acquisition_cost), 0) as assets_value")
            ->get();

        $names = $this->groupNames($groupBy, $rows->pluck('group_key'));

        return $rows
            ->map(fn ($row) => [
                'key' => $row->group_key,
                'label' => $names[$row->group_key] ?? 'Tanpa '.self::GROUPS[$groupBy],
                'count' => (int) $row->assets_count,
                'value' => (float) $row->assets_value,
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Nama tiap kelompok. Status dan kondisi dibaca dari enumnya; sisanya dari tabel
     * masternya, dan hanya id yang benar-benar muncul yang ditanyakan.
     *
     * @param  Collection<int, mixed>  $keys
     * @return array<int|string, string>
     */
    private function groupNames(string $groupBy, Collection $keys): array
    {
        $ids = $keys->filter()->unique()->values();

        return match ($groupBy) {
            'status' => AssetStatus::labels(),
            'condition' => AssetCondition::labels(),
            'category' => AssetCategory::query()->whereIn('id', $ids)->pluck('name', 'id')->all(),
            'department' => Department::query()->whereIn('id', $ids)->pluck('name', 'id')->all(),
            default => Branch::query()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        };
    }

    /**
     * Penyaring yang dipakai, dalam bentuk yang bisa dibaca manusia — untuk kepala
     * halaman PDF dan lembar keterangan Excel. Berkas laporan yang beredar lewat
     * surel tanpa keterangan filternya gampang dibaca sebagai "seluruh aset".
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function filterLabels(array $filters, string $groupBy): array
    {
        return [
            'Kategori' => $this->nameOf(AssetCategory::class, $filters['category'] ?? null) ?? 'Semua kategori',
            'Lokasi' => $this->nameOf(Branch::class, $filters['branch'] ?? null) ?? 'Semua lokasi',
            'Divisi' => $this->nameOf(Department::class, $filters['department'] ?? null) ?? 'Semua divisi',
            'Status' => AssetStatus::tryFrom((string) ($filters['status'] ?? ''))?->label() ?? 'Semua status',
            'Kondisi' => AssetCondition::tryFrom((string) ($filters['condition'] ?? ''))?->label() ?? 'Semua kondisi',
            'Garansi' => match ($filters['warranty'] ?? null) {
                'expiring' => 'Berakhir ≤30 hari',
                'expired' => 'Sudah lewat',
                default => 'Semua',
            },
            'Pencarian' => (string) ($filters['search'] ?? '') ?: '-',
            'Dikelompokkan per' => self::GROUPS[self::resolveGroup($groupBy)],
        ];
    }

    /** @param  class-string<Model>  $model */
    private function nameOf(string $model, mixed $id): ?string
    {
        return $id ? $model::query()->whereKey($id)->value('name') : null;
    }
}
