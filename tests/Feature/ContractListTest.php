<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * HR pusat: melihat semua lokasi (punya "employees.view.all"). $scoped=true membuat
 * user tanpa view.all, jadi hanya melihat kontrak karyawan dalam cakupannya.
 */
function contractViewer(bool $scoped = false): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['employees.view', 'employees.export'];

    if (! $scoped) {
        $permissions[] = User::SCOPE_BYPASS_EMPLOYEES;
    }

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Permission::findOrCreate(User::SCOPE_BYPASS_EMPLOYEES, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function makeContract(Employee $employee, string $number, ?string $end, string $status = 'active'): void
{
    $employee->contracts()->create([
        'contract_number' => $number,
        'contract_type' => $end === null ? 'PKWTT' : 'PKWT',
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => $end,
        'status' => $status,
    ]);
}

test('the contract list renders and the expiring filter narrows the rows', function () {
    $user = contractViewer();

    $soon = Employee::query()->create(['full_name' => 'Segera Habis', 'employment_status' => 'active']);
    makeContract($soon, 'CTR-SOON', now()->addDays(20)->toDateString());

    $far = Employee::query()->create(['full_name' => 'Masih Lama', 'employment_status' => 'active']);
    makeContract($far, 'CTR-FAR', now()->addDays(200)->toDateString());

    // Unfiltered: both contracts appear.
    $this->actingAs($user)->get('/employees/contracts')
        ->assertOk()
        ->assertSee('CTR-SOON')
        ->assertSee('CTR-FAR');

    // Filter ≤30 hari: only the soon-expiring one.
    $this->actingAs($user)->get('/employees/contracts?filter=expiring_30')
        ->assertOk()
        ->assertSee('CTR-SOON')
        ->assertDontSee('CTR-FAR');
});

test('the expired filter shows lapsed active contracts', function () {
    $user = contractViewer();

    $lapsed = Employee::query()->create(['full_name' => 'Kedaluwarsa', 'employment_status' => 'active']);
    makeContract($lapsed, 'CTR-LAPSED', now()->subDays(5)->toDateString());

    $running = Employee::query()->create(['full_name' => 'Berjalan', 'employment_status' => 'active']);
    makeContract($running, 'CTR-RUN', now()->addDays(90)->toDateString());

    $this->actingAs($user)->get('/employees/contracts?filter=expired')
        ->assertOk()
        ->assertSee('CTR-LAPSED')
        ->assertDontSee('CTR-RUN');
});

test('the contract list can be exported to xlsx honouring the filter', function () {
    Excel::fake();
    $user = contractViewer();

    $this->actingAs($user)->get('/employees/contracts/export?filter=expiring_30')
        ->assertOk();

    Excel::assertDownloaded('kontrak-'.now()->format('Y-m-d').'.xlsx');
});

test('a scoped user only sees contracts of employees in their scope', function () {
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $jkt = Branch::query()->create(['code' => 'JKT', 'name' => 'Jakarta', 'is_active' => true]);

    $mine = Employee::query()->create(['full_name' => 'Karyawan Surabaya', 'branch_id' => $sby->id, 'employment_status' => 'active']);
    makeContract($mine, 'CTR-SBY', now()->addDays(15)->toDateString());

    $other = Employee::query()->create(['full_name' => 'Karyawan Jakarta', 'branch_id' => $jkt->id, 'employment_status' => 'active']);
    makeContract($other, 'CTR-JKT', now()->addDays(15)->toDateString());

    $user = contractViewer(scoped: true);
    $user->accessBranches()->sync([$sby->id]);

    $this->actingAs($user)->get('/employees/contracts')
        ->assertOk()
        ->assertSee('CTR-SBY')
        ->assertDontSee('CTR-JKT');
});
