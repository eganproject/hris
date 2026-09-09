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
        ->assertSee('Handphone')
        // 2 laptop + 1 handphone, total nilai 18 juta.
        ->assertSee('Rp 18.000.000');
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

    $this->actingAs(assetReportUser())->get(route('reports.assets', ['category' => $f['phoneCategory']->id]))
        ->assertOk()
        ->assertSee('Handphone hostlive')
        ->assertDontSee('Laptop Dell')
        // Hanya handphone yang terhitung, jadi totalnya bukan lagi 18 juta.
        ->assertSee('Rp 3.000.000')
        ->assertDontSee('Rp 18.000.000');
});

test('laporan hanya memuat aset di dalam cakupan penggunanya', function () {
    $f = assetReportFixture();

    // Pengguna yang cakupannya hanya Head Office tidak boleh melihat aset Surabaya,
    // baik di daftarnya maupun di angka rekapnya.
    $user = assetReportUser(['reports.assets.view', 'reports.assets.export']);
    $user->accessBranches()->sync([$f['ho']->id]);

    $this->actingAs($user)->get(route('reports.assets'))
        ->assertOk()
        ->assertSee('Laptop Dell')
        ->assertDontSee('Handphone hostlive')
        ->assertSee('Rp 15.000.000');
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

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk();

    // Bawaannya memang ringkas.
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

    $response = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk();

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
        ->get(route('reports.assets', ['per_page' => 10]))
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
        ->assertViewHas('view', 'grouped');
});

test('unduhan excel membawa lembar datar dan lembar tercollapse sekaligus', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 2);

    Excel::fake();

    $this->actingAs(assetReportUser())->get(route('reports.assets.export'))->assertOk();

    Excel::assertDownloaded('register-aset-'.now()->format('Y-m-d').'.xlsx', function (AssetRegisterExport $export) {
        $titles = array_map(fn ($sheet) => $sheet->title(), $export->sheets());

        // Keduanya harus ada: yang ringkas untuk dibaca, yang datar untuk diolah.
        return in_array('Ringkas per Nama', $titles, true)
            && in_array('Register Aset', $titles, true);
    });
});

test('nama yang hanya punya satu unit tampil langsung, tanpa tombol buka', function () {
    $f = assetReportFixture();
    assetNamedUnits($f, 'iPhone XR', 2);

    $html = $this->actingAs(assetReportUser())->get(route('reports.assets'))->assertOk()->getContent();

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
        ->and($tersaring->viewData('nameGroups')->total())->toBe(1);

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
