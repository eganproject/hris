<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Services\PunchIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(private readonly PunchIngestionService $ingestion)
    {
    }

    public function index(Request $request): View
    {
        $devices = Device::query()
            ->with('branch')
            ->withCount(['mappings', 'punches'])
            ->orderBy('name')
            ->get();

        return view('attendance.devices.index', ['devices' => $devices]);
    }

    public function create(): View
    {
        return view('attendance.devices.create', [
            'device' => new Device(['timezone' => 'Asia/Jakarta', 'is_active' => true]),
            'branches' => Branch::query()->orderBy('name')->get(),
        ]);
    }

    public function store(DeviceRequest $request): RedirectResponse
    {
        Device::query()->create($request->payload());

        return redirect()->route('attendance.devices.index')->with('status', 'Perangkat berhasil didaftarkan.');
    }

    public function edit(Device $device): View
    {
        $device->load(['mappings.employee']);

        return view('attendance.devices.edit', [
            'device' => $device,
            'branches' => Branch::query()->orderBy('name')->get(),
            'employees' => Employee::query()->active()->orderBy('full_name')->get(),
        ]);
    }

    public function update(DeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update($request->payload());

        return redirect()->route('attendance.devices.index')->with('status', 'Perangkat berhasil diperbarui.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return redirect()->route('attendance.devices.index')->with('status', 'Perangkat dihapus.');
    }

    /**
     * Enroll a PIN → employee mapping for this device and back-fill past punches.
     */
    public function storeMapping(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'machine_user_id' => ['required', 'string', 'max:50'],
        ]);

        $this->ingestion->assignPin(
            Employee::findOrFail($data['employee_id']),
            $device,
            $data['machine_user_id'],
        );

        return redirect()->route('attendance.devices.edit', $device)->with('status', 'PIN dipetakan & punch lama dicocokkan ulang.');
    }

    public function destroyMapping(EmployeeDevice $mapping): RedirectResponse
    {
        $device = $mapping->device;
        $mapping->delete();

        return redirect()
            ->route($device ? 'attendance.devices.edit' : 'attendance.devices.index', $device)
            ->with('status', 'Pemetaan PIN dihapus.');
    }
}
