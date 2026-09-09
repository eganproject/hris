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
        'brand' => 'Ringkas per merek',
        'grouped' => 'Ringkas per nama',
        'detail' => 'Rinci per unit',
    ];

    public const DEFAULT_VIEW = 'brand';

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
     * Kunci merek dan model, dinormalkan seperti NAME_KEY tapi tahan kosong.
     *
     * Berbeda dari nama, kedua kolom ini boleh tidak diisi — dan menurut pemilik data
     * memang sering kosong. coalesce() membuat NULL dan string kosong jatuh ke kunci
     * yang sama, jadi "belum diisi" menjadi satu kelompok yang jelas ("Tanpa Merek",
     * "Tanpa Model") alih-alih berserakan atau hilang dari GROUP BY.
     *
     * Kelompok kosong itu sengaja tidak disembunyikan: besarnya adalah ukuran berapa
     * banyak data yang masih perlu dilengkapi.
     */
    private const BRAND_KEY = "lower(trim(coalesce(brand, '')))";

    private const MODEL_KEY = "lower(trim(coalesce(model, '')))";

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
     * @return LengthAwarePaginator<int, array{key: string, name: string, spellings: int, units: int, assets: Collection<int, Asset>}>
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
     * @return Collection<int, array{key: string, name: string, spellings: int, units: int}>
     */
    public function nameSummary(DataScope $scope, array $filters): Collection
    {
        return $this->nameGroupQuery($scope, $filters)->get()->map(fn ($row) => [
            'key' => (string) $row->group_key,
            'name' => (string) $row->display_name,
            'spellings' => (int) $row->spellings,
            'units' => (int) $row->units,
        ]);
    }

    /**
     * Aset dikumpulkan per merek, dan di dalam tiap merek dikumpulkan lagi per model.
     *
     * Sama seperti nameGroups(), yang dipaginasi adalah tingkat teratas — mereknya.
     * Satu merek selalu membawa seluruh unitnya dalam satu halaman, jadi jumlah yang
     * tertulis di kepala merek tidak pernah bisa berbeda dari isinya.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function brandGroups(DataScope $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->brandGroupQuery($scope, $filters)->paginate($perPage);

        $paginator->setCollection($this->attachBrandMembers($scope, $filters, collect($paginator->items())));

        return $paginator;
    }

    /**
     * Rekap merek + model tanpa unitnya, satu baris per pasangan — untuk lembar Excel
     * dan tabel PDF, yang memuat semuanya sekaligus dan tidak perlu barisan detailnya.
     *
     * Sengaja rata, bukan bersarang: di Excel baris yang rata bisa disaring dan
     * dipivot sendiri, sedangkan bentuk bersarang hanya enak dibaca dan buntu untuk
     * diolah lebih lanjut.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{brand: string, model: string, units: int, spellings: int}>
     */
    public function brandModelSummary(DataScope $scope, array $filters): Collection
    {
        return $this->base($scope, $filters)
            ->toBase()
            ->groupByRaw(self::BRAND_KEY.', '.self::MODEL_KEY)
            ->selectRaw(
                self::BRAND_KEY.' as brand_key, '.self::MODEL_KEY.' as model_key, '
                .'min(trim(brand)) as brand_name, min(trim(model)) as model_name, '
                .'count(distinct hex(model)) as spellings, count(*) as units'
            )
            ->get()
            ->map(fn ($row) => [
                'brand_key' => (string) $row->brand_key,
                'brand' => (string) ($row->brand_name ?? ''),
                'model_key' => (string) $row->model_key,
                'model' => (string) ($row->model_name ?? ''),
                'spellings' => (int) $row->spellings,
                'units' => (int) $row->units,
            ])
            ->pipe(function (Collection $rows): Collection {
                // Diurutkan di PHP, bukan di SQL. Baris satu merek harus berkumpul dan
                // mereknya berurut menurut TOTAL unitnya — angka yang tidak ada di
                // baris mana pun karena tiap baris hanya menghitung satu pasangan
                // merek+model. Mengambilnya di SQL berarti window function, yang
                // dukungannya berbeda-beda; jumlah pasangan merek×model selalu kecil,
                // jadi mengurutkannya di sini jauh lebih murah daripada risikonya.
                $totalPerBrand = $rows->groupBy('brand_key')->map->sum('units');

                return $rows
                    ->sortBy([
                        fn (array $a, array $b) => ($a['brand_key'] === '' ? 1 : 0) <=> ($b['brand_key'] === '' ? 1 : 0),
                        fn (array $a, array $b) => $totalPerBrand[$b['brand_key']] <=> $totalPerBrand[$a['brand_key']],
                        fn (array $a, array $b) => strcasecmp($a['brand'], $b['brand']),
                        fn (array $a, array $b) => ($a['model_key'] === '' ? 1 : 0) <=> ($b['model_key'] === '' ? 1 : 0),
                        fn (array $a, array $b) => $b['units'] <=> $a['units'],
                        fn (array $a, array $b) => strcasecmp($a['model'], $b['model']),
                    ])
                    ->values();
            });
    }

    /**
     * Satu baris per merek. Merek yang kosong selalu jatuh ke urutan paling akhir,
     * berapa pun banyaknya: ia bukan sebuah merek, melainkan pekerjaan yang tertunda,
     * dan menaruhnya di puncak daftar hanya karena isinya banyak akan mengubur merek
     * yang sebenarnya.
     *
     * @param  array<string, mixed>  $filters
     */
    private function brandGroupQuery(DataScope $scope, array $filters): QueryBuilder
    {
        return $this->base($scope, $filters)
            ->toBase()
            ->groupByRaw(self::BRAND_KEY)
            ->selectRaw(
                self::BRAND_KEY.' as group_key, min(trim(brand)) as display_name, '
                .'count(distinct hex(brand)) as spellings, count(*) as units'
            )
            ->orderByRaw($this->emptyLastOrder('group_key'))
            ->orderByDesc('units')
            ->orderBy('display_name');
    }

    /**
     * Urutkan kelompok berkunci kosong ke belakang.
     *
     * Yang dirujuk adalah ALIAS kolomnya, bukan ekspresi kuncinya diulang. MySQL
     * dengan only_full_group_by menolak ekspresi berisi kolom mentah di ORDER BY
     * sekalipun ekspresi itu persis yang dipakai GROUP BY — ia tidak menelusuri
     * kesamaannya ke dalam CASE WHEN. Aliasnya diterima, dan SQLite juga menerimanya.
     *
     * CASE WHEN, bukan perbandingan boolean seperti (alias = ''), supaya artinya sama
     * di kedua basis data tanpa bergantung pada bagaimana masing-masing
     * memperlakukan hasil perbandingan sebagai angka.
     */
    private function emptyLastOrder(string $alias): string
    {
        return "case when {$alias} = '' then 1 else 0 end asc";
    }

    /**
     * Isi tiap merek: seluruh unitnya, lalu dikumpulkan lagi per model.
     *
     * Tingkat model dibentuk di PHP dari unit yang memang sudah dimuat, bukan lewat
     * kueri agregat kedua — datanya sudah ada di tangan, dan menghitungnya lagi di
     * basis data hanya membuka peluang dua angka yang berbeda untuk hal yang sama.
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function attachBrandMembers(DataScope $scope, array $filters, Collection $rows): Collection
    {
        $keys = $rows->pluck('group_key')->all();

        $members = $keys === []
            ? collect()
            : $this->register($scope, $filters)
                ->select([
                    'assets.*',
                    DB::raw(self::BRAND_KEY.' as group_key'),
                    DB::raw(self::MODEL_KEY.' as model_key'),
                ])
                ->whereIn(DB::raw(self::BRAND_KEY), $keys)
                ->get()
                ->groupBy('group_key');

        return $rows->map(function ($row) use ($members) {
            $units = $members->get((string) $row->group_key, collect());

            return [
                'key' => (string) $row->group_key,
                'name' => (string) ($row->display_name ?? ''),
                'spellings' => (int) $row->spellings,
                'units' => (int) $row->units,
                'assets' => $units,
                'models' => $this->modelGroups($units),
            ];
        });
    }

    /**
     * Kelompok model di dalam satu merek, dengan aturan urutan yang sama seperti
     * mereknya: yang kosong paling belakang, sisanya terbanyak lebih dulu.
     *
     * @param  Collection<int, Asset>  $units
     * @return Collection<int, array<string, mixed>>
     */
    private function modelGroups(Collection $units): Collection
    {
        return $units
            ->groupBy(fn (Asset $asset) => (string) $asset->model_key)
            ->map(fn (Collection $rows, string $key) => [
                'key' => $key,
                'name' => (string) ($rows->map(fn (Asset $asset) => trim((string) $asset->model))->filter()->first() ?? ''),
                // Dibandingkan mentah — persis seperti hitungan ejaan di SQL, yang
                // membandingkan byte supaya beda huruf besar-kecil tidak lolos.
                'spellings' => $rows->map(fn (Asset $asset) => (string) $asset->model)->unique()->count(),
                'units' => $rows->count(),
                'assets' => $rows->values(),
            ])
            ->sortBy([
                fn (array $a, array $b) => ($a['key'] === '' ? 1 : 0) <=> ($b['key'] === '' ? 1 : 0),
                fn (array $a, array $b) => $b['units'] <=> $a['units'],
                fn (array $a, array $b) => strcasecmp($a['name'], $b['name']),
            ])
            ->values();
    }

    /**
     * Satu baris per nama: berapa unit dan berapa variasi ejaannya.
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
                .'count(distinct hex(name)) as spellings, count(*) as units'
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
            'assets' => $members->get((string) $row->group_key, collect()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     *                                         Nilai perolehan sengaja tidak ikut dihitung di mana pun pada laporan ini:
     *                                         register aset menjawab "barang apa, di mana, dipegang siapa, spesifikasinya
     *                                         apa", bukan berapa nilainya. Angkanya tetap tersimpan di master aset.
     * @return array{total: int, warranty_expiring: int, assigned: int, in_use: int}
     */
    public function summary(DataScope $scope, array $filters): array
    {
        $base = fn () => $this->base($scope, $filters);

        return [
            'total' => $base()->count(),
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
     * Rekap per satu sumbu: berapa aset di tiap kelompok.
     *
     * Dihitung dengan satu GROUP BY lalu namanya dicarikan setelahnya, bukan lewat
     * join — dengan begitu aturan cakupan di base() tetap satu-satunya yang membatasi
     * baris, dan tidak ada join yang diam-diam ikut menyaring atau menggandakan.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{key: int|string|null, label: string, count: int}>
     */
    public function groups(DataScope $scope, array $filters, string $groupBy): Collection
    {
        $groupBy = self::resolveGroup($groupBy);
        $column = self::GROUP_COLUMNS[$groupBy];

        $rows = $this->base($scope, $filters)
            ->reorder()
            ->groupBy($column)
            ->selectRaw("{$column} as group_key, count(*) as assets_count")
            ->get();

        $names = $this->groupNames($groupBy, $rows->pluck('group_key'));

        return $rows
            ->map(fn ($row) => [
                'key' => $row->group_key,
                'label' => $names[$row->group_key] ?? 'Tanpa '.self::GROUPS[$groupBy],
                'count' => (int) $row->assets_count,
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
