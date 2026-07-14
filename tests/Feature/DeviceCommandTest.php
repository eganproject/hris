<?php

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\User;
use App\Services\DeviceCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function commandAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['attendance.view', 'attendance.view.all', 'attendance.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.view.all', 'attendance.update']);

    return $user;
}

test('queueing a command from the device page creates a pending command', function () {
    $user = commandAdmin();
    $device = Device::query()->create(['serial_number' => 'CMD1', 'name' => 'M', 'is_active' => true]);

    $this->actingAs($user)->post("/attendance/devices/{$device->id}/commands", ['action' => 'reboot'])->assertRedirect();

    expect(DeviceCommand::query()->where('device_id', $device->id)->where('status', 'pending')->where('command', 'REBOOT')->count())->toBe(1);
});

test('getrequest delivers a pending command and marks it sent', function () {
    $device = Device::query()->create(['serial_number' => 'CMD2', 'name' => 'M', 'is_active' => true]);
    $command = app(DeviceCommandService::class)->reboot($device);

    $this->get('/iclock/getrequest?SN=CMD2')
        ->assertOk()
        ->assertSee("C:{$command->id}:REBOOT");

    expect($command->fresh()->status)->toBe('sent')
        ->and($command->fresh()->sent_at)->not->toBeNull();
});

test('getrequest returns OK when there are no pending commands', function () {
    Device::query()->create(['serial_number' => 'CMD2B', 'name' => 'M', 'is_active' => true]);

    $this->get('/iclock/getrequest?SN=CMD2B')->assertOk()->assertSee('OK');
});

test('devicecmd acknowledges a command result', function () {
    $device = Device::query()->create(['serial_number' => 'CMD3', 'name' => 'M', 'is_active' => true]);
    $command = app(DeviceCommandService::class)->check($device);

    $this->get('/iclock/getrequest?SN=CMD3')->assertOk();

    $this->call('POST', '/iclock/devicecmd?SN=CMD3', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], "ID={$command->id}&Return=0&CMD=DATA")
        ->assertOk();

    expect($command->fresh()->status)->toBe('done')
        ->and($command->fresh()->return_code)->toBe(0);
});

test('syncing users queues a command per mapped employee', function () {
    $user = commandAdmin();
    $device = Device::query()->create(['serial_number' => 'CMD4', 'name' => 'M', 'is_active' => true]);
    $e1 = Employee::query()->create(['full_name' => 'Andi', 'employment_status' => 'active']);
    $e2 = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create(['employee_id' => $e1->id, 'device_id' => $device->id, 'machine_user_id' => '1']);
    EmployeeDevice::query()->create(['employee_id' => $e2->id, 'device_id' => $device->id, 'machine_user_id' => '2']);

    $this->actingAs($user)->post("/attendance/devices/{$device->id}/commands", ['action' => 'sync_users'])->assertRedirect();

    expect(DeviceCommand::query()->where('device_id', $device->id)->count())->toBe(2)
        ->and(DeviceCommand::query()->where('command', 'like', 'DATA UPDATE USERINFO PIN=1%')->exists())->toBeTrue();
});
