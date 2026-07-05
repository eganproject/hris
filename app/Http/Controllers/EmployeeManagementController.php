<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ResignEmployeeRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class EmployeeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['branch_id', 'department_id', 'status', 'exit_reason', 'search']);
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $employees = Employee::query()
            ->with(['branch', 'department', 'jobPosition', 'currentContract'])
            ->byBranch($filters['branch_id'] ?? null)
            ->byDepartment($filters['department_id'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('employment_status', $status))
            ->when($filters['exit_reason'] ?? null, fn ($query, string $exitReason) => $query->where('exit_reason', $exitReason))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('join_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'branches' => Branch::query()->where('is_active', true)->orderBy('city')->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'summary' => [
                'total' => Employee::query()->count(),
                'active' => Employee::query()->active()->count(),
                'inactive' => Employee::query()->where('employment_status', 'inactive')->count(),
                'locations' => Branch::query()->where('is_active', true)->count(),
                'expiring_contracts' => EmployeeContract::query()->expiringWithin(30)->count(),
            ],
            'filters' => $filters,
            'perPage' => $perPage,
            'statuses' => Employee::employmentStatusLabels(),
            'exitReasons' => Employee::exitReasonLabels(),
        ]);
    }

    public function create(): View
    {
        return view('employees.create', $this->formOptions());
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $employee = Employee::query()->create($this->employeePayload($request));

            $employee->contracts()->create($this->contractPayload($request));
            $this->syncLoginAccount($request, $employee);
        });

        return redirect()
            ->route('employees.index')
            ->with('status', 'Data karyawan berhasil dibuat.');
    }

    public function show(Employee $employee): View
    {
        $employee->load(['branch', 'department', 'jobPosition', 'user.roles', 'contracts' => fn ($query) => $query->latest('start_date')]);

        return view('employees.show', ['employee' => $employee]);
    }

    public function edit(Employee $employee): View
    {
        $employee->load('currentContract', 'user.roles');

        return view('employees.edit', [
            'employee' => $employee,
            ...$this->formOptions(),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $oldPhotoPath = $employee->photo_path;

        DB::transaction(function () use ($request, $employee) {
            $employee->update($this->employeePayload($request));

            $contract = $employee->currentContract ?: $employee->contracts()->make();
            $contract->fill($this->contractPayload($request));
            $contract->save();

            $this->syncLoginAccount($request, $employee);
        });

        if ($request->hasFile('photo') && $oldPhotoPath && $oldPhotoPath !== $employee->photo_path) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()
            ->route('employees.index')
            ->with('status', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('status', 'Data karyawan berhasil dihapus.');
    }

    public function resign(ResignEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        DB::transaction(function () use ($request, $employee) {
            $employee->loadMissing('currentContract', 'user');

            $exitDate = $request->date('exit_date')->startOfDay();

            $employee->forceFill([
                'employment_status' => 'inactive',
                'exit_reason' => $request->validated('exit_reason'),
                'exit_date' => $exitDate,
                'exit_notes' => $request->validated('exit_notes'),
                'resigned_at' => $exitDate,
                'resignation_reason' => $request->validated('exit_notes'),
            ])->save();

            if ($employee->currentContract) {
                $employee->currentContract->forceFill([
                    'end_date' => $exitDate,
                    'status' => 'terminated',
                ])->save();
            }

            if ($employee->user) {
                $employee->user->forceFill(['is_active' => false])->save();
                DB::table('sessions')->where('user_id', $employee->user->id)->delete();
            }
        });

        return redirect()
            ->route('employees.index')
            ->with('status', 'Status akhir karyawan berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $branches = Branch::query()
            ->with(['activeDepartments' => fn ($query) => $query->where('departments.is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('city')
            ->orderBy('name')
            ->get();
        $departments = Department::query()
            ->with(['activeJobPositions' => fn ($query) => $query->where('job_positions.is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'branches' => $branches,
            'departments' => $departments,
            'jobPositions' => JobPosition::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'placementCatalog' => [
                'branches' => $branches->mapWithKeys(fn (Branch $branch) => [
                    $branch->id => $branch->activeDepartments->pluck('id')->values(),
                ]),
                'departments' => $departments->mapWithKeys(fn (Department $department) => [
                    $department->id => $department->activeJobPositions->pluck('id')->values(),
                ]),
            ],
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'statuses' => Employee::employmentStatusLabels(),
            'exitReasons' => Employee::exitReasonLabels(),
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
            'contractStatuses' => ['active', 'expired', 'terminated'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(EmployeeRequest $request): array
    {
        $payload = $request->safe()->only([
            'branch_id',
            'department_id',
            'job_position_id',
            'employee_number',
            'full_name',
            'email',
            'phone',
            'identity_number',
            'birth_date',
            'join_date',
            'employment_status',
            'address',
        ]);

        if ($request->hasFile('photo')) {
            $payload['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function contractPayload(EmployeeRequest $request): array
    {
        return [
            'contract_number' => $request->validated('contract_number'),
            'contract_type' => $request->validated('contract_type'),
            'start_date' => $request->validated('contract_start_date'),
            'end_date' => $request->validated('contract_end_date'),
            'status' => $request->validated('contract_status'),
            'notes' => $request->validated('contract_notes'),
        ];
    }

    private function syncLoginAccount(EmployeeRequest $request, Employee $employee): void
    {
        if (! $request->filled('email')) {
            return;
        }

        $employee->loadMissing('jobPosition.defaultRole', 'user');

        $role = Role::query()->find($request->validated('login_role_id')) ?: $employee->jobPosition?->defaultRole;
        $user = $employee->user;

        if (! $user && ! $request->filled('login_password')) {
            return;
        }

        $user ??= new User();

        $user->fill([
            'name' => $employee->full_name,
            'email' => $request->validated('email'),
        ]);

        if ($request->filled('login_password')) {
            $user->password = $request->validated('login_password');
        }

        $user->save();

        $employee->forceFill(['user_id' => $user->id])->save();

        if ($role) {
            $user->syncRoles([$role->name]);
        }
    }
}
