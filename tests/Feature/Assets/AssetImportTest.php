<?php

use App\Imports\AssetsImport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** @param list<string> $extra */
function assetImporter(array $extra = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['assets.view', 'assets.import', 'assets.view.all', ...$extra];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array<string, mixed> */
function importFixture(): array
{
    return [
        'category' => AssetCategory::query()->create([
            'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT',
            'requires_serial' => true, 'is_active' => true,
        ]),
        'branch' => Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]),
        'it' => Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]),
        'ops' => Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]),
    ];
}

/**
 * Bangun berkas .xlsx sungguhan berisi baris judul template plus baris yang diminta.
 *
 * Bukan CSV: aturan unggahan memeriksa jenis berkasnya, dan CSV polos terbaca
 * sebagai text/plain sehingga ditolak sebelum sempat dibaca importer — sama seperti
 * yang akan dialami pengguna kalau ia mengganti sendiri format templatenya.
 *
 * @param  list<array<string, string>>  $rows
 */
function assetWorkbook(array $rows): UploadedFile
{
    $columns = AssetsImport::columns();

    $sheet = new class($columns, $rows) implements FromArray, WithHeadings
    {
        public function __construct(private array $columns, private array $rows) {}

        public function array(): array
        {
            return array_map(
                fn (array $row) => array_map(fn ($column) => $row[$column['key']] ?? '', $this->columns),
                $this->rows,
            );
        }

        public function headings(): array
        {
            return array_map(fn ($column) => $column['header'], $this->columns);
        }
    };

    $path = tempnam(sys_get_temp_dir(), 'aset-').'.xlsx';
    file_put_contents($path, Excel::raw($sheet, ExcelWriter::XLSX));

    return new UploadedFile($path, 'aset.xlsx', null, null, true);
}

test('template impor memuat seluruh kolom dan lembar petunjuknya', function () {
    Excel::fake();

    $this->actingAs(assetImporter())->get(route('assets.import.template'))->assertOk();

    Excel::assertDownloaded('template-import-aset.xlsx');
});

test('baris yang sah tersimpan beserta kode aset otomatis', function () {
    $f = importFixture();

    $this->actingAs(assetImporter())
        ->post(route('assets.import'), ['file' => assetWorkbook([[
            'nama_aset' => 'Laptop Dell', 'kategori' => 'Laptop', 'nomor_seri' => 'SN-1',
            'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT',
            'divisi_kedua' => 'Operasional', 'status' => 'Tersedia', 'kondisi' => 'Baik',
            'tanggal_perolehan' => '2026-01-15', 'nilai_perolehan' => '15000000',
        ]])])
        ->assertRedirect(route('assets.index'));

    $asset = Asset::query()->firstOrFail();

    expect($asset->name)->toBe('Laptop Dell')
        ->and($asset->asset_code)->toStartWith('AST-LPT-HO-')
        ->and($asset->status->value)->toBe('available')
        // Lokasi sekarang dikosongkan berarti sama dengan lokasi pemiliknya.
        ->and($asset->current_branch_id)->toBe($f['branch']->id)
        ->and($asset->departments()->count())->toBe(2)
        ->and((float) $asset->acquisition_cost)->toBe(15000000.0);
});

test('satu baris salah membatalkan seluruh impor', function () {
    importFixture();

    $this->actingAs(assetImporter())
        ->post(route('assets.import'), ['file' => assetWorkbook([
            ['nama_aset' => 'Laptop A', 'kategori' => 'Laptop', 'nomor_seri' => 'SN-1', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT'],
            ['nama_aset' => 'Laptop B', 'kategori' => 'Monitor', 'nomor_seri' => 'SN-2', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT'],
        ])])
        ->assertRedirect()
        ->assertSessionHas('import_errors')
        // Berkasnya dikembalikan bertanda supaya tidak perlu dicari manual.
        ->assertSessionHas('import_error_token');

    // Baris pertama sebenarnya sah, tapi tidak ikut tersimpan: yang setengah masuk
    // jauh lebih merepotkan daripada yang gagal seluruhnya.
    expect(Asset::query()->count())->toBe(0);
});

test('master yang belum terdaftar ditolak sambil menyebut menunya', function () {
    importFixture();

    $this->actingAs(assetImporter())
        ->post(route('assets.import'), ['file' => assetWorkbook([
            ['nama_aset' => 'Kursi', 'kategori' => 'Furnitur', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT'],
        ])]);

    $errors = session('import_errors');

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('Kategori "Furnitur" belum terdaftar')
        ->and($errors[0])->toContain('Kategori Aset');
});

test('status dipegang tidak bisa diimpor dan alasannya dijelaskan', function () {
    importFixture();

    $this->actingAs(assetImporter())
        ->post(route('assets.import'), ['file' => assetWorkbook([
            ['nama_aset' => 'Laptop A', 'kategori' => 'Laptop', 'nomor_seri' => 'SN-1', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT', 'status' => 'Dipegang'],
        ])]);

    expect(session('import_errors')[0])->toContain('Serah Terima Aset');
    expect(Asset::query()->count())->toBe(0);
});

test('nomor seri wajib bila kategorinya menandainya, dan tidak boleh kembar', function () {
    importFixture();
    $importer = assetImporter();

    // Kategori Laptop menandai nomor seri sebagai wajib.
    $this->actingAs($importer)->post(route('assets.import'), ['file' => assetWorkbook([
        ['nama_aset' => 'Laptop A', 'kategori' => 'Laptop', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT'],
    ])]);

    expect(session('import_errors')[0])->toContain('mewajibkan Nomor Seri');

    // Dua baris dengan nomor seri sama di file yang sama.
    $this->actingAs($importer)->post(route('assets.import'), ['file' => assetWorkbook([
        ['nama_aset' => 'Laptop A', 'kategori' => 'Laptop', 'nomor_seri' => 'SN-9', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT'],
        ['nama_aset' => 'Laptop B', 'kategori' => 'Laptop', 'nomor_seri' => 'SN-9', 'lokasi_pemilik' => 'Head Office', 'divisi_pemilik' => 'IT'],
    ])]);

    expect(session('import_errors')[0])->toContain('dipakai lebih dari sekali');
    expect(Asset::query()->count())->toBe(0);
});

test('pengimpor bercakupan tidak bisa memasukkan aset ke lokasi di luar cakupannya', function () {
    $f = importFixture();
    $lain = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    // Tanpa assets.view.all, cakupannya ditentukan lokasi kerja yang diberikan.
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['assets.view', 'assets.import', 'assets.view.all'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $importer = User::factory()->create();
    $importer->givePermissionTo(['assets.view', 'assets.import']);
    $importer->accessBranches()->sync([$f['branch']->id]);

    $this->actingAs($importer)->post(route('assets.import'), ['file' => assetWorkbook([
        ['nama_aset' => 'Laptop SBY', 'kategori' => 'Laptop', 'nomor_seri' => 'SN-5', 'lokasi_pemilik' => 'Surabaya', 'divisi_pemilik' => 'IT'],
    ])]);

    expect(session('import_errors')[0])->toContain('di luar cakupan akses Anda');
    expect(Asset::query()->count())->toBe(0);
});

test('hak menambah aset tidak dengan sendirinya memberi hak mengimpor', function () {
    importFixture();

    $penambah = assetImporter();
    $penambah->revokePermissionTo('assets.import');

    $this->actingAs($penambah)->get(route('assets.import.template'))->assertForbidden();
    $this->actingAs($penambah)->post(route('assets.import'), [])->assertForbidden();
});
