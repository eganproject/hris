<?php

use App\Models\Employee;
use App\Models\OvertimeApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function overtimeExporter(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $permissions = ['overtime.view', User::SCOPE_BYPASS_ATTENDANCE];
    foreach ($permissions as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

function overtimeRow(Employee $employee, string $date, string $status, int $requested, int $approved = 0, array $extra = []): OvertimeApproval
{
    return OvertimeApproval::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'start_time' => '17:00',
        'end_time' => '19:00',
        'requested_minutes' => $requested,
        'approved_minutes' => $approved,
        'reason' => 'Kejar target.',
        'status' => $status,
        'requested_at' => now(),
        ...$extra,
    ]);
}

function downloadOvertimeWorkbook(User $user, string $query): Spreadsheet
{
    $response = test()->actingAs($user)->get('/attendance/overtime/export?'.$query);
    $response->assertOk();

    return IOFactory::load($response->getFile()->getPathname());
}

test('the overtime page shows an export button that keeps the active filters', function () {
    $this->actingAs(overtimeExporter())
        ->get('/attendance/overtime?month=2026-06&status=approved')
        ->assertOk()
        ->assertSee('Export Excel')
        ->assertSee('/attendance/overtime/export?month=2026-06&amp;status=approved', false);
});

test('the export produces a recap, a detail sheet and an info sheet for the month', function () {
    $budi = Employee::query()->create(['full_name' => 'Budi', 'employee_number' => 'K-002', 'employment_status' => 'active']);
    $ana = Employee::query()->create(['full_name' => 'Ana', 'employee_number' => 'K-001', 'employment_status' => 'active']);

    overtimeRow($budi, '2026-06-02', 'approved', 120, 90);
    overtimeRow($budi, '2026-06-05', 'pending', 60);
    overtimeRow($ana, '2026-06-03', 'approved', 150, 150);
    overtimeRow($ana, '2026-06-04', 'rejected', 30);
    overtimeRow($ana, '2026-07-01', 'approved', 600, 600); // bulan lain, tidak ikut

    $response = $this->actingAs(overtimeExporter())->get('/attendance/overtime/export?month=2026-06');
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('lembur-2026-06.xlsx');

    $book = IOFactory::load($response->getFile()->getPathname());

    expect($book->getSheetNames())->toBe(['Rekap per Karyawan', 'Rincian Lembur', 'Keterangan']);

    // Rekap: judul di baris 1, judul kolom di baris 4, urut nama, total di akhir.
    $recap = $book->getSheet(0);
    expect($recap->getCell('A1')->getValue())->toContain('JUNI 2026')
        ->and($recap->getCell('C4')->getValue())->toBe('Nama Karyawan')
        ->and($recap->getCell('C5')->getValue())->toBe('Ana')
        ->and($recap->getCell('C6')->getValue())->toBe('Budi')
        ->and($recap->getCell('H5')->getValue())->toBe(2)   // jumlah pengajuan Ana
        ->and($recap->getCell('K5')->getValue())->toBe(1)   // Ana ditolak 1
        ->and($recap->getCell('N5')->getValue())->toEqual(2.5)
        ->and($recap->getCell('N6')->getValue())->toEqual(1.5)
        ->and($recap->getCell('A7')->getValue())->toBe('TOTAL')
        ->and($recap->getCell('N7')->getCalculatedValue())->toEqual(4.0)
        ->and($recap->getCell('M7')->getFormattedValue())->toBe('4:00')
        ->and($recap->getAutoFilter()->getRange())->toBe('A4:N6');

    // Rincian: urut tanggal, tanggal asli Excel, disetujui kosong bila belum disetujui.
    $detail = $book->getSheet(1);
    expect($detail->getCell('E5')->getValue())->toBe('Budi')
        ->and($detail->getCell('B5')->getFormattedValue())->toBe('02/06/2026')
        ->and($detail->getCell('J5')->getFormattedValue())->toBe('2:00')
        ->and($detail->getCell('L5')->getFormattedValue())->toBe('1:30')
        ->and($detail->getCell('N5')->getValue())->toBe('Disetujui')
        ->and($detail->getCell('N8')->getValue())->toBe('Menunggu')
        ->and($detail->getCell('M8')->getValue())->toBeNull()
        ->and($detail->getCell('A9')->getValue())->toBe('TOTAL')
        ->and($detail->getCell('M9')->getCalculatedValue())->toEqual(4.0);

    $info = $book->getSheet(2);
    expect($info->getCell('B4')->getValue())->toBe('Juni 2026')
        ->and($info->getCell('B12')->getValue())->toEqual(4);
});

test('the export follows the status and search filters of the page', function () {
    $budi = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $ana = Employee::query()->create(['full_name' => 'Ana', 'employment_status' => 'active']);
    overtimeRow($budi, '2026-06-02', 'approved', 60, 60);
    overtimeRow($budi, '2026-06-03', 'pending', 60);
    overtimeRow($ana, '2026-06-02', 'approved', 60, 60);

    $book = downloadOvertimeWorkbook(overtimeExporter(), 'month=2026-06&status=approved&search=Bud');

    $detail = $book->getSheet(1);
    expect($detail->getCell('E5')->getValue())->toBe('Budi')
        ->and($detail->getCell('A6')->getValue())->toBe('TOTAL')
        ->and($detail->getCell('A2')->getValue())->toContain('Status: Disetujui');
});

test('an empty month still exports a valid workbook', function () {
    $book = downloadOvertimeWorkbook(overtimeExporter(), 'month=2026-06');

    expect($book->getSheet(0)->getCell('A5')->getValue())->toBe('TOTAL')
        ->and($book->getSheet(1)->getCell('A5')->getValue())->toBe('TOTAL');
});

test('the export requires the overtime permission', function () {
    $this->actingAs(User::factory()->create())
        ->get('/attendance/overtime/export?month=2026-06')
        ->assertForbidden();
});
