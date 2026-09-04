<?php

namespace App\Imports;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Mengimpor master aset dari template Excel yang menyertainya.
 *
 * Validasinya semua-atau-tidak sama sekali: seluruh baris diperiksa lebih dulu, dan
 * baru disimpan bila satu file itu bersih. Daftar aset yang setengah masuk jauh
 * lebih merepotkan daripada yang gagal seluruhnya — orang harus menebak baris mana
 * yang sudah terlanjur ada sebelum berani mencoba lagi.
 *
 * Bedanya dengan impor karyawan: kategori, lokasi kerja, dan divisi di sini HARUS
 * sudah terdaftar, tidak dibuat otomatis dari isian file. Kategori membawa prefix
 * yang ikut membentuk kode aset permanen, dan prefix itu tidak bisa ditebak dari
 * sebuah nama di spreadsheet — menebaknya berarti mencetak kode yang salah pada
 * barang yang kodenya tidak bisa dikoreksi lagi.
 */
class AssetsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithMultipleSheets
{
    /**
     * Hanya lembar pertama yang berisi data. Tanpa ini, importer dijalankan pada
     * setiap lembar di buku kerja — termasuk "Petunjuk Pengisian" milik templatenya
     * sendiri, yang lalu dilaporkan sebagai baris aset yang rusak.
     *
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /** @var list<array{row: ?int, column: ?string, message: string}> */
    private array $rowErrors = [];

    private int $imported = 0;

    /**
     * Cakupan pengimpor, dalam huruf kecil. null berarti sumbu itu tidak dibatasi;
     * sebuah daftar berarti tiap baris wajib menyebut salah satunya — seorang officer
     * cabang tidak boleh memasukkan aset atas nama cabang lain.
     *
     * @param  list<string>|null  $allowedBranches
     * @param  list<string>|null  $allowedDepartments
     */
    public function __construct(
        private readonly ?array $allowedBranches = null,
        private readonly ?array $allowedDepartments = null,
    ) {}

    /**
     * Kesalahan yang enak dibaca untuk modal, masing-masing diawali nomor barisnya.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return array_map(
            fn (array $e) => $e['row'] !== null ? "Baris {$e['row']}: {$e['message']}" : $e['message'],
            $this->rowErrors,
        );
    }

    /**
     * Masalah yang sama, terstruktur, untuk menyusun berkas rincian kesalahan.
     *
     * @return list<array{row: ?int, column: ?string, message: string}>
     */
    public function rowErrors(): array
    {
        return $this->rowErrors;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    private function addError(?int $row, string $message, ?string $column = null): void
    {
        $this->rowErrors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }

    /**
     * Keterangan kolom, dipakai bersama template dan lembar petunjuknya supaya baris
     * judul, panduan, dan importer ini tidak pernah berbeda isi.
     *
     * @return list<array{key: string, header: string, required: bool, example: string, desc: string}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'kode_aset', 'header' => 'Kode Aset', 'required' => false, 'example' => 'AST-LPT-HO-0012', 'desc' => 'Dibuat otomatis oleh sistem saat aset disimpan. Kolom ini hanya untuk data hasil ekspor — isinya diabaikan saat impor.'],
            ['key' => 'nama_aset', 'header' => 'Nama Aset', 'required' => true, 'example' => 'Laptop Dell Latitude 5420', 'desc' => 'Nama barangnya.'],
            ['key' => 'kategori', 'header' => 'Kategori', 'required' => true, 'example' => 'Laptop', 'desc' => 'Nama kategori yang SUDAH terdaftar di menu Kategori Aset. Tidak dibuat otomatis, karena kategori membawa prefix yang ikut membentuk kode aset.'],
            ['key' => 'merek', 'header' => 'Merek', 'required' => false, 'example' => 'Dell', 'desc' => 'Opsional.'],
            ['key' => 'model', 'header' => 'Model', 'required' => false, 'example' => 'Latitude 5420', 'desc' => 'Opsional.'],
            ['key' => 'nomor_seri', 'header' => 'Nomor Seri', 'required' => false, 'example' => 'SN-0001', 'desc' => 'Wajib bila kategorinya menandai nomor seri sebagai wajib. Harus unik di seluruh daftar aset.'],
            ['key' => 'spesifikasi', 'header' => 'Spesifikasi', 'required' => false, 'example' => 'Core i5, RAM 16 GB', 'desc' => 'Opsional.'],
            ['key' => 'lokasi_pemilik', 'header' => 'Lokasi Pemilik', 'required' => true, 'example' => 'Head Office', 'desc' => 'Lokasi kerja yang MEMILIKI aset. Ikut membentuk kode aset dan tidak berubah saat barangnya dipindah. Harus sudah terdaftar.'],
            ['key' => 'lokasi_sekarang', 'header' => 'Lokasi Sekarang', 'required' => false, 'example' => 'Head Office', 'desc' => 'Tempat barangnya berada saat ini. Dikosongkan berarti sama dengan Lokasi Pemilik.'],
            ['key' => 'divisi_pemilik', 'header' => 'Divisi Pemilik', 'required' => true, 'example' => 'IT', 'desc' => 'Divisi yang memiliki aset. Harus sudah terdaftar.'],
            ['key' => 'divisi_kedua', 'header' => 'Divisi Kedua', 'required' => false, 'example' => '', 'desc' => 'Opsional, untuk aset yang dimiliki bersama dua divisi. Harus berbeda dari Divisi Pemilik.'],
            ['key' => 'status', 'header' => 'Status', 'required' => false, 'example' => 'Tersedia', 'desc' => 'Tersedia, Draft, Perawatan, Hilang, atau Tidak Dipakai. Dikosongkan berarti Tersedia. "Dipegang" tidak bisa diimpor — status itu hanya lahir dari penyerahan aset, supaya selalu punya pemegang yang tercatat.'],
            ['key' => 'kondisi', 'header' => 'Kondisi', 'required' => false, 'example' => 'Baik', 'desc' => 'Baru, Baik, Cukup, Rusak, atau Tidak Layak. Dikosongkan berarti Baik.'],
            ['key' => 'tanggal_perolehan', 'header' => 'Tanggal Perolehan', 'required' => false, 'example' => '2026-01-15', 'desc' => 'Format YYYY-MM-DD. Tidak boleh di masa depan.'],
            ['key' => 'nilai_perolehan', 'header' => 'Nilai Perolehan', 'required' => false, 'example' => '15000000', 'desc' => 'Angka rupiah, tanpa titik atau koma pemisah ribuan.'],
            ['key' => 'garansi_berakhir', 'header' => 'Garansi Berakhir', 'required' => false, 'example' => '2029-01-15', 'desc' => 'Format YYYY-MM-DD. Tidak boleh mendahului Tanggal Perolehan.'],
            ['key' => 'catatan', 'header' => 'Catatan', 'required' => false, 'example' => '', 'desc' => 'Opsional.'],
        ];
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->addError(null, 'File tidak berisi data aset. Pastikan data diisi mulai baris ke-2.');

            return;
        }

        $lookups = $this->buildLookups();

        // Nomor seri harus unik terhadap yang sudah ada DAN terhadap sesamanya di
        // dalam file yang sama — dua baris bernomor seri sama akan lolos kalau hanya
        // basis data yang ditanya.
        $seenSerials = [];

        /** @var list<array<string, mixed>> $prepared */
        $prepared = [];

        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2; // baris judul adalah baris 1
            $data = $this->validateRow($this->normalize($rawRow), $rowNumber, $lookups, $seenSerials);

            if ($data !== null) {
                $prepared[] = $data;
            }
        }

        if ($this->rowErrors !== []) {
            return; // semua-atau-tidak: file yang belum bersih tidak disimpan sebagian
        }

        $this->persist($prepared);
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @param  array<string, bool>  $seenSerials
     * @return array<string, mixed>|null
     */
    private function validateRow(Collection $row, int $rowNumber, array $lookups, array &$seenSerials): ?array
    {
        $before = count($this->rowErrors);
        $add = fn (string $message, ?string $column = null) => $this->addError($rowNumber, $message, $column);

        $name = trim((string) $row->get('nama_aset'));

        if ($name === '') {
            $add('Nama Aset wajib diisi.', 'Nama Aset');
        }

        $categoryId = $this->lookup($row->get('kategori'), $lookups['categories'], 'Kategori', $add, 'Kategori Aset');
        $owningId = $this->branch($row->get('lokasi_pemilik'), $lookups, 'Lokasi Pemilik', $add, true);

        // Lokasi sekarang boleh kosong: barang yang belum pernah dipindah ada di
        // tempat pemiliknya.
        $currentRaw = trim((string) $row->get('lokasi_sekarang'));
        $currentId = $currentRaw === ''
            ? $owningId
            : $this->branch($currentRaw, $lookups, 'Lokasi Sekarang', $add, true);

        $departmentId = $this->department($row->get('divisi_pemilik'), $lookups, 'Divisi Pemilik', $add, true);
        $secondRaw = trim((string) $row->get('divisi_kedua'));
        $secondId = $secondRaw === '' ? null : $this->department($secondRaw, $lookups, 'Divisi Kedua', $add, true);

        if ($secondId !== null && $secondId === $departmentId) {
            $add('Divisi Kedua harus berbeda dari Divisi Pemilik.', 'Divisi Kedua');
        }

        $status = $this->status(trim((string) $row->get('status')), $add);
        $condition = $this->condition(trim((string) $row->get('kondisi')), $add);

        $serial = $this->serial(trim((string) $row->get('nomor_seri')), $categoryId, $lookups, $seenSerials, $add);

        $acquiredAt = $this->parseDate(trim((string) $row->get('tanggal_perolehan')), 'Tanggal Perolehan', $add);

        if ($acquiredAt && $acquiredAt->isAfter(CarbonImmutable::today())) {
            $add('Tanggal Perolehan tidak boleh di masa depan.', 'Tanggal Perolehan');
        }

        $warranty = $this->parseDate(trim((string) $row->get('garansi_berakhir')), 'Garansi Berakhir', $add);

        if ($warranty && $acquiredAt && $warranty->isBefore($acquiredAt)) {
            $add('Garansi Berakhir tidak boleh mendahului Tanggal Perolehan.', 'Garansi Berakhir');
        }

        $cost = $this->cost(trim((string) $row->get('nilai_perolehan')), $add);

        if (count($this->rowErrors) > $before) {
            return null;
        }

        return [
            'category_id' => $categoryId,
            'name' => $name,
            'brand' => trim((string) $row->get('merek')) ?: null,
            'model' => trim((string) $row->get('model')) ?: null,
            'serial_number' => $serial,
            'specification' => trim((string) $row->get('spesifikasi')) ?: null,
            'owning_branch_id' => $owningId,
            'current_branch_id' => $currentId,
            'department_id' => $departmentId,
            'second_department_id' => $secondId,
            'status' => $status,
            'condition' => $condition,
            'acquired_at' => $acquiredAt?->toDateString(),
            'acquisition_cost' => $cost,
            'warranty_expires_at' => $warranty?->toDateString(),
            'notes' => trim((string) $row->get('catatan')) ?: null,
        ];
    }

    /** @param  list<array<string, mixed>>  $prepared */
    private function persist(array $prepared): void
    {
        DB::transaction(function () use ($prepared): void {
            foreach ($prepared as $data) {
                $second = $data['second_department_id'];
                unset($data['second_department_id']);

                $asset = Asset::query()->create($data);

                // Divisi utama sudah dilekatkan sendiri oleh model; yang kedua
                // ditambahkan di sini bila ada.
                if ($second !== null) {
                    $asset->departments()->syncWithoutDetaching([$second]);
                }

                $this->imported++;
            }
        });
    }

    /**
     * Nama → id untuk seluruh master yang dirujuk file, plus nomor seri yang sudah
     * terpakai. Diambil sekali di depan, bukan per baris: file berisi seribu aset
     * jika tidak akan menghasilkan ribuan query yang menanyakan hal yang sama.
     *
     * @return array<string, mixed>
     */
    private function buildLookups(): array
    {
        $byLowerName = fn ($rows) => $rows->mapWithKeys(
            fn ($row) => [strtolower(trim((string) $row->name)) => $row->id],
        )->all();

        return [
            'categories' => $byLowerName(AssetCategory::query()->active()->get(['id', 'name'])),
            'branches' => $byLowerName(Branch::query()->get(['id', 'name'])),
            'departments' => $byLowerName(Department::query()->where('is_active', true)->get(['id', 'name'])),
            'serials' => Asset::query()->withTrashed()->whereNotNull('serial_number')
                ->pluck('serial_number')
                ->map(fn ($serial) => strtolower(trim((string) $serial)))
                ->flip()
                ->all(),
            'requires_serial' => AssetCategory::query()->pluck('requires_serial', 'id')->all(),
        ];
    }

    /**
     * Cari id dari sebuah nama. Yang tidak ketemu ditolak dengan menyebut menunya,
     * bukan sekadar "tidak valid" — supaya orangnya tahu harus membuka apa.
     *
     * @param  array<string, int>  $map
     */
    private function lookup(mixed $value, array $map, string $label, callable $add, string $menu): ?int
    {
        $name = trim((string) $value);

        if ($name === '') {
            $add("{$label} wajib diisi.", $label);

            return null;
        }

        $id = $map[strtolower($name)] ?? null;

        if ($id === null) {
            $add("{$label} \"{$name}\" belum terdaftar. Tambahkan dulu di menu {$menu}, lalu ulangi impornya.", $label);
        }

        return $id;
    }

    /** @param  array<string, mixed>  $lookups */
    private function branch(mixed $value, array $lookups, string $label, callable $add, bool $required): ?int
    {
        $name = trim((string) $value);

        if ($name === '' && ! $required) {
            return null;
        }

        $id = $this->lookup($name, $lookups['branches'], $label, $add, 'Lokasi Kerja');

        if ($id !== null && $this->allowedBranches !== null && ! in_array(strtolower($name), $this->allowedBranches, true)) {
            $add("{$label} \"{$name}\" berada di luar cakupan akses Anda.", $label);

            return null;
        }

        return $id;
    }

    /** @param  array<string, mixed>  $lookups */
    private function department(mixed $value, array $lookups, string $label, callable $add, bool $required): ?int
    {
        $name = trim((string) $value);

        if ($name === '' && ! $required) {
            return null;
        }

        $id = $this->lookup($name, $lookups['departments'], $label, $add, 'Divisi');

        if ($id !== null && $this->allowedDepartments !== null && ! in_array(strtolower($name), $this->allowedDepartments, true)) {
            $add("{$label} \"{$name}\" berada di luar cakupan akses Anda.", $label);

            return null;
        }

        return $id;
    }

    /**
     * Status yang boleh diimpor hanya yang bisa dipilih manusia di formulir.
     * "Dipegang" sengaja ditolak: ia harus selalu berpasangan dengan catatan siapa
     * pemegangnya, dan sebuah kolom di spreadsheet tidak membawa itu.
     */
    private function status(string $value, callable $add): string
    {
        if ($value === '') {
            return AssetStatus::Available->value;
        }

        foreach (AssetStatus::MANUAL as $status) {
            if (strcasecmp($status->label(), $value) === 0 || strcasecmp($status->value, $value) === 0) {
                return $status->value;
            }
        }

        $allowed = implode(', ', array_map(fn (AssetStatus $s) => $s->label(), AssetStatus::MANUAL));

        if (strcasecmp(AssetStatus::Assigned->label(), $value) === 0) {
            $add('Status "Dipegang" tidak bisa diimpor. Masukkan asetnya sebagai Tersedia, lalu serahkan lewat menu Serah Terima Aset agar pemegangnya ikut tercatat.', 'Status');
        } else {
            $add("Status \"{$value}\" tidak dikenal. Pilih salah satu: {$allowed}.", 'Status');
        }

        return AssetStatus::Available->value;
    }

    private function condition(string $value, callable $add): string
    {
        if ($value === '') {
            return AssetCondition::Good->value;
        }

        foreach (AssetCondition::cases() as $condition) {
            if (strcasecmp($condition->label(), $value) === 0 || strcasecmp($condition->value, $value) === 0) {
                return $condition->value;
            }
        }

        $allowed = implode(', ', array_map(fn (AssetCondition $c) => $c->label(), AssetCondition::cases()));
        $add("Kondisi \"{$value}\" tidak dikenal. Pilih salah satu: {$allowed}.", 'Kondisi');

        return AssetCondition::Good->value;
    }

    /**
     * @param  array<string, mixed>  $lookups
     * @param  array<string, bool>  $seen
     */
    private function serial(string $value, ?int $categoryId, array $lookups, array &$seen, callable $add): ?string
    {
        if ($value === '') {
            if ($categoryId !== null && ! empty($lookups['requires_serial'][$categoryId])) {
                $add('Kategori ini mewajibkan Nomor Seri.', 'Nomor Seri');
            }

            return null;
        }

        $key = strtolower($value);

        if (isset($lookups['serials'][$key])) {
            $add("Nomor Seri \"{$value}\" sudah terdaftar pada aset lain (termasuk aset yang sudah diarsipkan).", 'Nomor Seri');
        } elseif (isset($seen[$key])) {
            $add("Nomor Seri \"{$value}\" dipakai lebih dari sekali di file ini.", 'Nomor Seri');
        }

        $seen[$key] = true;

        return $value;
    }

    private function cost(string $value, callable $add): ?string
    {
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            $add('Nilai Perolehan harus berupa angka dan tidak boleh negatif. Tulis tanpa titik atau koma pemisah ribuan.', 'Nilai Perolehan');

            return null;
        }

        return $value;
    }

    /**
     * Tanggal boleh datang sebagai teks maupun sebagai angka serial Excel — sel yang
     * diformat tanggal tidak pernah sampai ke sini sebagai "2026-01-15".
     */
    private function parseDate(string $value, string $label, callable $add): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            $add("{$label} tidak terbaca sebagai tanggal. Pakai format YYYY-MM-DD, misalnya 2026-01-15.", $label);

            return null;
        }
    }

    /** Baris apa pun bentuknya diseragamkan jadi Collection berkunci nama kolom. */
    private function normalize(mixed $row): Collection
    {
        return $row instanceof Collection ? $row : collect((array) $row);
    }
}
