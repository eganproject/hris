<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
