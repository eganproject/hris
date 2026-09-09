<?php

use App\Enums\AssetStatus;
use App\Exports\AssetRegisterExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\AssetRegisterReport;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** @param list<string> $permissions */
function assetReportUser(array $permissions = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = $permissions ?: ['reports.assets.view', 'reports.assets.export', 'assets.view.all'];

    foreach ([...$permissions, 'reports.assets.view', 'reports.assets.export', 'assets.view', 'assets.view.all'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Permission::findOrCreate(User::SCOPE_BYPASS_ASSETS, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** Dua kategori, dua lokasi, dan aset dengan nilai perolehan yang berbeda-beda. */
function assetReportFixture(): array
{
    $laptop = AssetCategory::query()->create(['code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true]);
    $phone = AssetCategory::query()->create(['code' => 'HP', 'name' => 'Handphone', 'asset_prefix' => 'HP', 'is_active' => true]);
    $ho = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $it = Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);

    $make = fn (AssetCategory $category, Branch $branch, string $name, string $status, ?string $cost) => Asset::query()->create([
        'category_id' => $category->id,
        'name' => $name,
        'owning_branch_id' => $branch->id,
        'current_branch_id' => $branch->id,
        'department_id' => $it->id,
        'status' => $status,
        'condition' => 'good',
        'acquisition_cost' => $cost,
    ])->refresh();

    return [
        'laptopA' => $make($laptop, $ho, 'Laptop Dell', AssetStatus::Available->value, '10000000'),
        'laptopB' => $make($laptop, $ho, 'Laptop Lenovo', AssetStatus::Available->value, '5000000'),
        'phone' => $make($phone, $sby, 'Handphone hostlive', AssetStatus::InUse->value, '3000000'),
        'laptop' => $laptop,
        'phoneCategory' => $phone,
        'ho' => $ho,
        'sby' => $sby,
        'it' => $it,
    ];
}

test('register aset menampilkan rekap per kategori beserta totalnya', function () {
    assetReportFixture();

    $this->actingAs(assetReportUser())->get(route('reports.assets'))
        ->assertOk()
        ->assertSee('Register Aset')
        ->assertSee('Rekap per Kategori')
        ->assertSee('Laptop')
        ->assertSee('Handphone');

    // Rekapnya dibuktikan lewat cacahnya, bukan rupiah: laporan ini memang tidak
    // lagi menghitung nilai perolehan.
    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'));
    $groups = collect($response->viewData('groups'));

    expect($response->viewData('summary')['total'])->toBe(3)
        ->and($groups->firstWhere('label', 'Laptop')['count'])->toBe(2)
        ->and($groups->firstWhere('label', 'Handphone')['count'])->toBe(1);
});

test('rekap bisa dikelompokkan per sumbu lain', function () {
    assetReportFixture();

    $this->actingAs(assetReportUser())->get(route('reports.assets', ['group' => 'status']))
        ->assertOk()
        ->assertSee('Rekap per Status')
        ->assertSee('Tersedia')
        ->assertSee('Dipakai');
});

test('sumbu pengelompokan yang tidak dikenal jatuh ke bawaannya, bukan galat', function () {
    assetReportFixture();

    $this->actingAs(assetReportUser())->get(route('reports.assets', ['group' => 'drop table']))
        ->assertOk()
        ->assertSee('Rekap per Kategori');
});

test('penyaring laporan mempersempit rekap dan daftarnya sekaligus', function () {
    $f = assetReportFixture();

    $response = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['category' => $f['phoneCategory']->id]))
        ->assertOk()
        ->assertSee('Handphone hostlive')
        ->assertDontSee('Laptop Dell');

    // Rekap dan daftarnya menyusut bersama-sama, bukan cuma salah satunya.
    expect($response->viewData('summary')['total'])->toBe(1)
        ->and($response->viewData('groups'))->toHaveCount(1);
});

test('laporan hanya memuat aset di dalam cakupan penggunanya', function () {
    $f = assetReportFixture();

    // Pengguna yang cakupannya hanya Head Office tidak boleh melihat aset Surabaya,
    // baik di daftarnya maupun di angka rekapnya.
    $user = assetReportUser(['reports.assets.view', 'reports.assets.export']);
    $user->accessBranches()->sync([$f['ho']->id]);

    $response = $this->actingAs($user)->get(route('reports.assets'))
        ->assertOk()
        ->assertSee('Laptop Dell')
        ->assertDontSee('Handphone hostlive');

    expect($response->viewData('summary')['total'])->toBe(2);
});

test('unduhan excel dan pdf mengikuti cakupan serta penyaring yang sama', function () {
    $f = assetReportFixture();

    $user = assetReportUser(['reports.assets.view', 'reports.assets.export']);
    $user->accessBranches()->sync([$f['ho']->id]);

    $excel = $this->actingAs($user)->get(route('reports.assets.export'));
    $excel->assertOk();
    expect($excel->headers->get('content-disposition'))->toContain('register-aset-');

    $pdf = $this->actingAs($user)->get(route('reports.assets.pdf'));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');
});

test('melihat laporan tidak dengan sendirinya boleh mengunduhnya', function () {
    assetReportFixture();

    $viewer = assetReportUser(['reports.assets.view', 'assets.view.all']);

    $this->actingAs($viewer)->get(route('reports.assets'))->assertOk()->assertDontSee('>Excel<', false);
    $this->actingAs($viewer)->get(route('reports.assets.export'))->assertForbidden();
    $this->actingAs($viewer)->get(route('reports.assets.pdf'))->assertForbidden();
});

test('tanpa izin laporan aset, halamannya dan kartunya tertutup', function () {
    assetReportFixture();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['reports.assets.view', 'reports.assets.export', 'reports.leave.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $outsider = User::factory()->create();
    $outsider->givePermissionTo('reports.leave.view');

    $this->actingAs($outsider)->get(route('reports.assets'))->assertForbidden();
    $this->actingAs($outsider)->get(route('reports.index'))->assertOk()->assertDontSee('Register Aset');
});

test('pemegang aset ikut tercatat di laporan', function () {
    $f = assetReportFixture();

    $employee = Employee::query()->create([
        'full_name' => 'Budi Pemegang', 'employment_status' => 'active',
        'branch_id' => $f['ho']->id, 'department_id' => $f['it']->id,
    ]);

    $f['laptopA']->assignments()->create([
        'employee_id' => $employee->id,
        'assigned_at' => today(),
        'condition_out' => 'good',
        'assigned_by' => assetReportUser()->id,
    ]);
    $f['laptopA']->forceFill(['status' => AssetStatus::Assigned->value])->save();

    $this->actingAs(assetReportUser())->get(route('reports.assets'))
        ->assertOk()
        ->assertSee('Budi Pemegang');
});

test('dipegang dan dipakai dihitung sebagai dua angka terpisah, bukan satu', function () {
    $f = assetReportFixture();

    $employee = Employee::query()->create([
        'full_name' => 'Budi Pemegang', 'employment_status' => 'active',
        'branch_id' => $f['ho']->id, 'department_id' => $f['it']->id,
    ]);

    $f['laptopA']->assignments()->create([
        'employee_id' => $employee->id,
        'assigned_at' => today(),
        'condition_out' => 'good',
        'assigned_by' => assetReportUser()->id,
    ]);
    $f['laptopA']->forceFill(['status' => AssetStatus::Assigned->value])->save();

    // Fixture-nya: 1 Dipegang (laptopA), 1 Dipakai (phone), 1 Tersedia (laptopB).
    $summary = app(AssetRegisterReport::class)->summary(DataScope::forAssets(assetReportUser()), []);

    expect($summary['assigned'])->toBe(1)
        ->and($summary['in_use'])->toBe(1);

    $this->actingAs(assetReportUser())->get(route('reports.assets'))
        ->assertOk()
        ->assertSee('Dipegang karyawan')
        ->assertSee('Dipakai bersama')
        // Kolom Pemegang barang pakai bersama tidak boleh tampil sama dengan aset
        // menganggur: kosongnya disengaja, bukan luput dicatat.
        ->assertSee('Pakai bersama');
});

/** Beberapa unit bernama sama, dengan ejaan yang sengaja tidak seragam. */
function assetNamedUnits(array $f, string $name, int $count, array $spellings = []): void
{
    foreach (range(1, $count) as $i) {
        Asset::query()->create([
            'category_id' => $f['laptop']->id,
            'name' => $spellings[$i - 1] ?? $name,
            'owning_branch_id' => $f['ho']->id,
            'current_branch_id' => $f['ho']->id,
            'department_id' => $f['it']->id,
            'status' => AssetStatus::Available->value,
            'condition' => 'good',
            'acquisition_cost' => '5000000',
        ]);
    }
}

test('tampilan ringkas menggabungkan aset yang bernama sama menjadi satu baris', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 2);

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets', ['view' => 'grouped']))->assertOk();

    expect($response->viewData('view'))->toBe('grouped');

    $groups = $response->viewData('nameGroups');
    $iphone = collect($groups->items())->firstWhere('key', 'iphone xr');

    expect($iphone)->not->toBeNull()
        ->and($iphone['units'])->toBe(2)
        ->and($iphone['assets'])->toHaveCount(2);

    $response->assertSee('2 unit');
});

test('ejaan yang tidak seragam tetap tergabung dan ditandai untuk dirapikan', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 3, ['iPhone XR', 'IPHONE XR', '  iPhone XR  ']);

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets', ['view' => 'grouped']))->assertOk();

    // Dicari lewat kunci grupnya, bukan nama tampilnya: ketika ejaannya beragam,
    // ejaan mana yang terpilih untuk ditampilkan bergantung pada collation basis
    // datanya. Yang dijamin sama di mana pun adalah kunci, jumlah, dan peringatannya.
    $iphone = collect($response->viewData('nameGroups')->items())->firstWhere('key', 'iphone xr');

    expect($iphone['units'])->toBe(3)
        // Nama tampilnya rapi tanpa spasi tepi, tapi ketidakseragamannya tidak ikut
        // hilang — itu yang harus diperbaiki di master aset.
        ->and($iphone['name'])->toBe(trim($iphone['name']))
        ->and($iphone['spellings'])->toBe(3);

    $response->assertSee('3 ejaan berbeda');
});

test('satu nama tidak pernah terbelah oleh paginasi', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 4);

    // Satu grup per halaman: kalau yang dipaginasi unit dan bukan nama, iPhone XR
    // akan muncul sebagai beberapa grup berisi satu unit — angka yang salah di
    // laporan yang dipakai menghitung barang.
    $response = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['view' => 'grouped', 'per_page' => 10]))
        ->assertOk();

    $groups = $response->viewData('nameGroups');
    $iphone = collect($groups->items())->firstWhere('key', 'iphone xr');

    expect($groups->perPage())->toBe(10)
        ->and($iphone['units'])->toBe(4)
        ->and($iphone['assets'])->toHaveCount(4)
        // Yang dihitung paginator adalah nama, bukan unit: 3 dari fixture + iPhone XR.
        ->and($groups->total())->toBe(4);
});

test('tampilan rinci tetap bisa dipilih dan memuat satu baris per unit', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 2);

    $response = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['view' => 'detail']))
        ->assertOk();

    expect($response->viewData('view'))->toBe('detail')
        ->and($response->viewData('nameGroups'))->toBeNull()
        ->and($response->viewData('assets')->total())->toBe(5);
});

test('nilai tampilan yang tidak dikenal jatuh ke bawaannya, bukan galat', function () {
    assetReportFixture();

    $this->actingAs(assetReportUser())->get(route('reports.assets', ['view' => 'sembarang']))
        ->assertOk()
        ->assertViewHas('view', 'brand');
});

test('unduhan excel membawa lembar datar dan lembar tercollapse sekaligus', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 2);

    Excel::fake();

    $this->actingAs(assetReportUser())->get(route('reports.assets.export'))->assertOk();

    Excel::assertDownloaded('register-aset-'.now()->format('Y-m-d').'.xlsx', function (AssetRegisterExport $export) {
        $titles = array_map(fn ($sheet) => $sheet->title(), $export->sheets());

        // Keduanya harus ada: yang ringkas untuk dibaca, yang datar untuk diolah.
        return in_array('Ringkas per Merek', $titles, true)
            && in_array('Register Aset', $titles, true);
    });
});

test('nama yang hanya punya satu unit tampil langsung, tanpa tombol buka', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 2);

    $html = $this->actingAs(assetReportUser())->get(route('reports.assets', ['view' => 'grouped']))->assertOk()->getContent();

    // Fixture-nya berisi tiga nama yang masing-masing satu unit; hanya iPhone XR
    // yang berjumlah dua. Jadi tepat satu tombol buka yang boleh ada.
    expect(substr_count($html, 'data-group-toggle='))->toBe(1);

    // Yang tunggal tetap membawa kode asetnya di baris yang sama — kalau ia hanya
    // ditampilkan sebagai nama, keterangan unitnya justru hilang dibanding sebelumnya.
    $laptop = Asset::query()->where('name', 'Laptop Dell')->firstOrFail();

    expect($html)->toContain($laptop->asset_code)
        ->and($html)->not->toContain('grup-'.md5('laptop dell'));
});

test('baris rekap menautkan ke halaman yang tersaring ke baris itu', function () {
    $f = assetReportFixture();

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk();

    // Tautan membawa penyaring kategorinya, bukan parameter baru — supaya kartu,
    // rekap, dan daftar tetap dihitung dari himpunan yang sama.
    $response->assertSee(route('reports.assets', ['category' => $f['phoneCategory']->id]), false);

    $tersaring = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['category' => $f['phoneCategory']->id]))
        ->assertOk();

    // Kategori Handphone hanya berisi satu aset, dan seluruh halaman ikut menyusut.
    expect($tersaring->viewData('summary')['total'])->toBe(1)
        ->and($tersaring->viewData('groups'))->toHaveCount(1)
        ->and($tersaring->viewData('brandGroups')->total())->toBe(1);

    $tersaring->assertSee('Handphone hostlive')->assertDontSee('Laptop Dell');
});

/** Potongan HTML tabel Rekap saja, supaya assertion tidak tertipu tautan di bagian lain halaman. */
function assetRecapHtml(string $html): string
{
    $start = strpos($html, 'Rekap per');
    $end = strpos($html, 'Daftar Aset</h2>');

    return substr($html, $start, $end - $start);
}

test('baris rekap yang sedang aktif menautkan ke pelepasan penyaringnya', function () {
    $f = assetReportFixture();

    $html = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['category' => $f['phoneCategory']->id]))
        ->assertOk()
        ->getContent();

    $rekap = assetRecapHtml($html);

    preg_match_all('/href="([^"]*)"/', $rekap, $matches);

    // Diklik lagi berarti lepas: tanpa ini pengguna terjebak, karena rekapnya sudah
    // menyusut jadi satu baris dan tidak ada kategori lain yang bisa diklik.
    expect($rekap)->toContain('disaring')
        ->and($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $href) {
        expect($href)->not->toContain('category=');
    }
});

test('kelompok tanpa nilai tidak bisa diklik karena penyaringnya tidak bisa menyatakannya', function () {
    $f = assetReportFixture();

    Asset::query()->create([
        'category_id' => $f['laptop']->id,
        'name' => 'Proyektor tanpa divisi',
        'owning_branch_id' => $f['ho']->id,
        'current_branch_id' => $f['ho']->id,
        'department_id' => null,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ]);

    $response = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['group' => 'department']))
        ->assertOk();

    $tanpa = collect($response->viewData('groups'))->firstWhere('key', null);

    expect($tanpa)->not->toBeNull()
        ->and($tanpa['count'])->toBe(1);

    $rekap = assetRecapHtml($response->getContent());

    // Barisnya ada, tapi labelnya tidak dibungkus tautan: "Tanpa Divisi" tidak bisa
    // dinyatakan sebagai nilai penyaring, jadi mengkliknya akan menampilkan daftar
    // yang isinya bukan baris itu.
    preg_match('/<td[^>]*>(?:(?!<\/td>).)*Tanpa Divisi Pemilik(?:(?!<\/td>).)*<\/td>/s', $rekap, $cell);

    expect($cell)->not->toBeEmpty()
        ->and($cell[0])->not->toContain('<a ');

    // Divisi yang punya nilai tetap bisa diklik, jadi ketidakadaan tautan di atas
    // memang karena null-nya, bukan karena tautannya hilang seluruhnya.
    expect($rekap)->toContain('href=');
});

test('kolom nilai perolehan diganti spesifikasi, dan angkanya tidak muncul di mana pun', function () {
    $f = assetReportFixture();

    $f['laptopA']->forceFill(['specification' => 'Core i7, RAM 16GB, SSD 512GB'])->save();

    $html = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk()->getContent();

    expect($html)->toContain('Spesifikasi')
        ->and($html)->toContain('Core i7, RAM 16GB, SSD 512GB')
        // Fixture-nya bernilai 10jt, 5jt, dan 3jt. Tidak satu pun boleh terbawa,
        // termasuk lewat kartu ringkas dan tabel rekap yang dulu menjumlahkannya.
        ->and($html)->not->toContain('Nilai Perolehan')
        ->and($html)->not->toContain('Nilai perolehan:')
        ->and($html)->not->toContain('Rp 10.000.000')
        ->and($html)->not->toContain('Rp 18.000.000');

    // Perhitungannya juga berhenti, bukan cuma tampilannya yang disembunyikan.
    $summary = app(AssetRegisterReport::class)->summary(DataScope::forAssets(assetReportUser()), []);

    expect($summary)->not->toHaveKey('value');
});

test('spesifikasi yang panjang dipotong tapi tetap terbawa utuh untuk dibaca', function () {
    $f = assetReportFixture();

    $panjang = 'Spesifikasi sangat panjang '.str_repeat('dengan banyak sekali keterangan ', 20);
    $f['laptopA']->forceFill(['specification' => $panjang])->save();

    $html = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk()->getContent();

    // Yang tampil dipotong supaya satu baris tidak meregangkan seluruh tabel,
    // tapi teks utuhnya tetap ada di title agar tidak ada keterangan yang hilang.
    expect($html)->toContain(Str::limit($panjang, 120))
        ->and($html)->toContain(e($panjang))
        ->and($html)->not->toContain('>'.e($panjang).'<');
});

test('unit di dalam grup menampilkan merek dan model, kode asetnya jadi keterangan kecil', function () {
    $f = assetReportFixture();

    $unit = fn (?string $brand, ?string $model, ?string $sn) => Asset::query()->create([
        'category_id' => $f['laptop']->id, 'name' => 'iPhone XR',
        'brand' => $brand, 'model' => $model, 'serial_number' => $sn,
        'owning_branch_id' => $f['ho']->id, 'current_branch_id' => $f['ho']->id,
        'department_id' => $f['it']->id,
        'status' => AssetStatus::Available->value, 'condition' => 'good',
    ]);

    $lengkap = $unit('Apple', 'MRY62', 'SN-A1');
    $merekSaja = $unit('Apple', null, null);
    $modelSaja = $unit(null, 'MRY72', null);
    $kosong = $unit(null, null, null);

    $html = $this->actingAs(assetReportUser())->get(route('reports.assets', ['view' => 'grouped']))->assertOk()->getContent();

    expect($html)->toContain('Apple MRY62')
        // Yang cuma punya salah satunya tidak boleh menyisakan spasi menggantung.
        ->and($html)->not->toContain('Apple  ')
        ->and($html)->not->toContain(' MRY72</p>');

    // Yang merek dan modelnya kosong tetap terbaca lewat kode asetnya — barisnya
    // tidak boleh berakhir kosong hanya karena datanya belum diisi.
    foreach ([$lengkap, $merekSaja, $modelSaja, $kosong] as $asset) {
        expect($html)->toContain($asset->refresh()->asset_code);
    }

    expect($html)->toContain('SN SN-A1');
});

/** Satu unit dengan merek dan model yang ditentukan. */
function assetBranded(array $f, string $name, ?string $brand, ?string $model): Asset
{
    return Asset::query()->create([
        'category_id' => $f['laptop']->id,
        'name' => $name,
        'brand' => $brand,
        'model' => $model,
        'owning_branch_id' => $f['ho']->id,
        'current_branch_id' => $f['ho']->id,
        'department_id' => $f['it']->id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ]);
}

test('bawaannya mengelompokkan per merek, lalu per model di dalamnya', function () {
    $f = assetReportFixture();
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY72');

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk();

    expect($response->viewData('view'))->toBe('brand');

    $apple = collect($response->viewData('brandGroups')->items())->firstWhere('key', 'apple');

    expect($apple['units'])->toBe(3)
        ->and($apple['models'])->toHaveCount(2)
        // Model terbanyak lebih dulu.
        ->and($apple['models'][0]['name'])->toBe('MRY62')
        ->and($apple['models'][0]['units'])->toBe(2)
        ->and($apple['models'][1]['name'])->toBe('MRY72')
        ->and($apple['models'][1]['units'])->toBe(1);
});

test('merek dan model yang kosong jadi kelompok tersendiri di urutan paling bawah', function () {
    $f = assetReportFixture();
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone 11', 'Apple', null);

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk();

    $brands = collect($response->viewData('brandGroups')->items());

    // Fixture-nya berisi tiga aset tanpa merek — lebih banyak daripada Apple yang
    // hanya dua — tapi ia tetap harus di bawah: "belum diisi" bukan sebuah merek.
    expect($brands->last()['key'])->toBe('')
        ->and($brands->last()['units'])->toBe(3)
        ->and($brands->firstWhere('key', 'apple')['units'])->toBe(2);

    $apple = $brands->firstWhere('key', 'apple');

    expect($apple['models']->last()['key'])->toBe('');

    $response->assertSee('Tanpa Merek')
        ->assertSee('Tanpa Model')
        ->assertSee('Kolom Merek belum diisi di master aset.');
});

test('merek dan model yang cuma punya satu unit tidak diberi tombol buka', function () {
    $f = assetReportFixture();
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone 11', 'Apple', 'MRY72');
    assetBranded($f, 'Latitude', 'Dell', 'E5420');

    $html = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk()->getContent();

    // Merek yang bisa dibuka: Apple (4 unit) dan Tanpa Merek (3 unit). Dell hanya satu
    // unit, jadi ia baris biasa tanpa tombol.
    expect(substr_count($html, 'data-brand-toggle='))->toBe(2)
        // Model yang bisa dibuka hanya MRY62 yang berisi dua unit; MRY72 satu unit,
        // dan Tanpa Model di bawah Tanpa Merek berisi tiga sehingga ikut punya tombol.
        ->and(substr_count($html, 'data-model-toggle='))->toBe(2)
        ->and($html)->toContain('Latitude');
});

test('satu merek tidak pernah terbelah oleh paginasi', function () {
    $f = assetReportFixture();

    foreach (range(1, 4) as $i) {
        assetBranded($f, 'iPhone XR', 'Apple', 'MRY'.$i);
    }

    $response = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['per_page' => 10]))
        ->assertOk();

    $brands = $response->viewData('brandGroups');
    $apple = collect($brands->items())->firstWhere('key', 'apple');

    // Yang dihitung paginator adalah merek: Apple dan Tanpa Merek.
    expect($brands->total())->toBe(2)
        ->and($apple['units'])->toBe(4)
        ->and($apple['assets'])->toHaveCount(4)
        ->and($apple['models'])->toHaveCount(4);
});

test('ejaan merek yang tidak seragam tetap tergabung dan ditandai', function () {
    $f = assetReportFixture();
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone XR', 'APPLE', 'MRY62');

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk();

    $apple = collect($response->viewData('brandGroups')->items())->firstWhere('key', 'apple');

    expect($apple['units'])->toBe(2)
        ->and($apple['spellings'])->toBe(2);

    $response->assertSee('Ditulis dalam 2 ejaan berbeda — rapikan di master aset.');
});

test('tampilan per nama tetap tersedia sebagai pilihan', function () {
    $f = assetReportFixture();
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone XR', 'Dell', 'E5420');

    $response = $this->actingAs(assetReportUser())
        ->get(route('reports.assets', ['view' => 'grouped']))
        ->assertOk();

    // Dua merek berbeda, tapi satu nama — mode lama masih menggabungkannya.
    $iphone = collect($response->viewData('nameGroups')->items())->firstWhere('key', 'iphone xr');

    expect($response->viewData('brandGroups'))->toBeNull()
        ->and($iphone['units'])->toBe(2);

    $response->assertSee('Ringkas per merek')->assertSee('Ringkas per nama');
});

test('lembar excel ringkas berisi baris merek dan model, bukan nama', function () {
    $f = assetReportFixture();
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');
    assetBranded($f, 'iPhone XR', 'Apple', 'MRY62');

    $report = app(AssetRegisterReport::class);
    $rows = $report->brandModelSummary(DataScope::forAssets(assetReportUser()), []);

    $apple = $rows->firstWhere('brand', 'Apple');

    expect($apple['model'])->toBe('MRY62')
        ->and($apple['units'])->toBe(2)
        // Baris tanpa merek tetap ada, dan tetap di urutan terakhir.
        ->and($rows->last()['brand'])->toBe('');

    Excel::fake();

    $this->actingAs(assetReportUser())->get(route('reports.assets.export'))->assertOk();

    Excel::assertDownloaded('register-aset-'.now()->format('Y-m-d').'.xlsx', function (AssetRegisterExport $export) {
        $titles = array_map(fn ($sheet) => $sheet->title(), $export->sheets());

        return in_array('Ringkas per Merek', $titles, true)
            && in_array('Register Aset', $titles, true);
    });
});
