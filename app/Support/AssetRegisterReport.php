<?php

namespace App\Support;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Bentuk daftar asetnya: satu baris per unit, atau satu baris per nama yang bisa
     * dibuka isinya.
     *
     * Sama seperti GROUPS, kuncinya ikut ke URL dan ke nama sheet ekspor.
     */
    public const VIEWS = [
        'grouped' => 'Ringkas per nama',
        'detail' => 'Rinci per unit',
    ];

    public const DEFAULT_VIEW = 'grouped';

    /**
     * Kunci penggabungan nama: huruf dikecilkan dan spasi tepinya dibuang.
     *
     * Kolom name adalah teks bebas, jadi "iPhone XR", "IPHONE XR", dan "iPhone XR "
     * adalah tiga tulisan untuk satu barang yang sama. Menormalkan kuncinya membuat
     * ketiganya berkumpul — dan membuat hasilnya sama di MySQL (yang collation-nya
     * biasanya sudah mengabaikan besar-kecil huruf) maupun SQLite (yang tidak).
     *
     * Yang TIDAK bisa diselesaikan di sini: "iPhone XR" vs "iPhone XR 64GB". Itu dua
     * tulisan yang memang berbeda, dan hanya disiplin penamaan yang bisa merapikannya.
     * Laporan ini justru menampilkannya sebagai dua grup berdampingan supaya
     * ketidakkonsistenannya kelihatan, bukan tertutup diam-diam.
     */
    private const NAME_KEY = 'lower(trim(name))';

    /**
     * Penyaring yang dipasang ketika sebuah baris rekap diklik.
     *
     * Tiga sumbu pertama menyaring kolom yang sama persis dengan yang dipakai
     * mengelompokkan, jadi setelah diklik rekapnya menyusut menjadi satu baris dengan
     * angka yang sama — betul-betul menelusuri ke bawah.
     *
     * Lokasi dan divisi tidak sepersis itu, dan ini disengaja. Penyaring lokasi
     * mencari lokasi pemilik ATAU lokasi sekarang supaya aset yang dititipkan ke
     * cabang lain tidak hilang dari kedua sisi, sementara rekapnya mengelompokkan
     * satu kolom saja; penyaring divisi juga ikut membaca divisi kedua. Hasil kliknya
     * karena itu bisa lebih luas daripada baris yang diklik. Membuat penyaring khusus
     * yang persis akan menghasilkan dua arti "lokasi" di satu halaman — yang lebih
     * membingungkan daripada satu penyaring yang perilakunya sudah dijelaskan.
     */
    public const GROUP_FILTERS = [
        'category' => 'category',
        'status' => 'status',
        'condition' => 'condition',
        'owning_branch' => 'branch',
        'current_branch' => 'branch',
        'department' => 'department',
    ];

    /** @var list<string> Sumbu yang penyaringnya menyaring persis kolom yang sama. */
    private const EXACT_GROUP_FILTERS = ['category', 'status', 'condition'];

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

    public static function resolveView(?string $view): string
    {
        return array_key_exists((string) $view, self::VIEWS) ? (string) $view : self::DEFAULT_VIEW;
    }

    /** Apakah mengklik baris rekap sumbu ini menghasilkan daftar yang persis sebesar barisnya. */
    public static function groupFilterIsExact(string $groupBy): bool
    {
        return in_array(self::resolveGroup($groupBy), self::EXACT_GROUP_FILTERS, true);
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
     * Aset yang bernama sama, dikumpulkan jadi satu baris yang bisa dibuka isinya.
     *
     * Yang dipaginasi adalah GRUPNYA, bukan unitnya — dan ini bukan pilihan gaya.
     * Kalau unit yang dipaginasi lalu digabung di tampilan, dua unit iPhone XR yang
     * kebetulan jatuh di halaman 1 dan 2 akan muncul sebagai dua grup berisi satu
     * unit. Sebuah laporan stock opname yang menulis "1" untuk barang yang ada dua
     * lebih berbahaya daripada daftar panjang yang jujur. Dengan memaginasi grup,
     * satu nama selalu utuh dalam satu halaman.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{key: string, name: string, spellings: int, units: int, value: float, assets: Collection<int, Asset>}>
     */
    public function nameGroups(DataScope $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->nameGroupQuery($scope, $filters)->paginate($perPage);

        $paginator->setCollection($this->attachMembers($scope, $filters, collect($paginator->items())));

        return $paginator;
    }

    /**
     * Rekap nama tanpa unitnya — untuk lembar Excel dan tabel PDF, yang memuat
     * seluruh grup sekaligus dan tidak perlu barisan detailnya.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{key: string, name: string, spellings: int, units: int, value: float}>
     */
    public function nameSummary(DataScope $scope, array $filters): Collection
    {
        return $this->nameGroupQuery($scope, $filters)->get()->map(fn ($row) => [
            'key' => (string) $row->group_key,
            'name' => (string) $row->display_name,
            'spellings' => (int) $row->spellings,
            'units' => (int) $row->units,
            'value' => (float) $row->units_value,
        ]);
    }

    /**
     * Satu baris per nama: berapa unit, berapa nilainya, dan berapa variasi ejaannya.
     *
     * Ketika satu nama ditulis beberapa cara, ejaan mana yang dipakai sebagai nama
     * tampil ditentukan collation basis data dan boleh berbeda antar mesin — yang
     * penting kelompoknya, jumlahnya, dan peringatan "N ejaan berbeda" yang menyuruh
     * merapikannya. Spasi tepinya dibuang supaya nama tampilnya tidak pernah terlihat
     * menjorok sendiri di tengah daftar.
     *
     * Terbanyak lebih dulu, lalu menurut abjad — barang yang menumpuk itulah yang
     * dicari orang saat membuka laporan ini, dan urutannya tetap sama di dua kali
     * cetak.
     *
     * @param  array<string, mixed>  $filters
     */
    private function nameGroupQuery(DataScope $scope, array $filters): QueryBuilder
    {
        return $this->base($scope, $filters)
            ->toBase()
            ->groupByRaw(self::NAME_KEY)
            ->selectRaw(
                self::NAME_KEY.' as group_key, min(trim(name)) as display_name, '
                // hex(), bukan count(distinct name) begitu saja: collation bawaan MySQL
                // mengabaikan besar-kecil huruf, jadi "iPhone XR" dan "IPHONE XR" akan
                // terhitung satu ejaan dan peringatannya tidak pernah muncul justru pada
                // kasus yang paling sering terjadi. Membandingkan bytenya membuat
                // hitungan ini sama persis di MySQL maupun SQLite.
                .'count(distinct hex(name)) as spellings, count(*) as units, '
                .'coalesce(sum(acquisition_cost), 0) as units_value'
            )
            ->orderByDesc('units')
            ->orderBy('display_name');
    }

    /**
     * Isi tiap grup: seluruh unit milik nama-nama yang ada di halaman ini.
     *
     * Kunci grupnya ikut dipilih dari SQL, bukan dihitung ulang di PHP. lower() milik
     * basis data dan mb_strtolower() milik PHP tidak selalu sepakat pada huruf
     * beraksen, dan kalau keduanya berbeda satu grup akan tampil kosong padahal
     * hitungannya bilang ada isinya.
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function attachMembers(DataScope $scope, array $filters, Collection $rows): Collection
    {
        $keys = $rows->pluck('group_key')->all();

        $members = $keys === []
            ? collect()
            : $this->register($scope, $filters)
                ->select(['assets.*', DB::raw(self::NAME_KEY.' as group_key')])
                ->whereIn(DB::raw(self::NAME_KEY), $keys)
                ->get()
                ->groupBy('group_key');

        return $rows->map(fn ($row) => [
            'key' => (string) $row->group_key,
            'name' => (string) $row->display_name,
            'spellings' => (int) $row->spellings,
            'units' => (int) $row->units,
            'value' => (float) $row->units_value,
            'assets' => $members->get((string) $row->group_key, collect()),
        ]);
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
