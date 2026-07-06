<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateExpiredContracts;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class EmployeeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->deactivateExpiredContractsDaily();

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

            $contract = $employee->contracts()->create($this->contractPayload($request));
            $this->syncLoginAccount($request, $employee);

            $employee->recordEvent('joined', 'Bergabung sebagai karyawan.', $employee->join_date);
            $employee->recordEvent(
                'contract_created',
                "Kontrak {$contract->contract_number} ({$contract->contract_type}) dibuat.",
                $contract->start_date,
                ['contract_number' => $contract->contract_number],
            );
        });

        return redirect()
            ->route('employees.index')
            ->with('status', 'Data karyawan berhasil dibuat.');
    }

    public function show(Employee $employee): View
    {
        $employee->load([
            'branch',
            'department',
            'jobPosition',
            'user.roles',
            'contracts' => fn ($query) => $query->latest('start_date'),
            'events' => fn ($query) => $query->latest('occurred_at')->latest('id'),
            'events.causer',
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
        ]);
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
        $wasActive = ! $employee->isInactive();
        $isExitViaEdit = $wasActive && $request->filled('exit_reason')
            && in_array($request->validated('contract_status'), EmployeeContract::closingStatuses(), true);

        DB::transaction(function () use ($request, $employee, $isExitViaEdit) {
            $employee->update($this->employeePayload($request));

            $contract = $employee->currentContract ?: $employee->contracts()->make();
            $contract->fill($this->contractPayload($request));
            $contract->save();

            $this->syncLoginAccount($request, $employee);

            // The contract was closed during this edit: process the exit here so the
            // user does not have to go to the detail page. The contract state was
            // already set above, so we don't sync it again.
            if ($isExitViaEdit) {
                $exitDate = $request->date('exit_date')->startOfDay();

                $employee->markAsExited(
                    $request->validated('exit_reason'),
                    $exitDate,
                    $request->validated('exit_notes'),
                    syncContract: false,
                );

                $employee->recordEvent(
                    'exited',
                    'Diproses keluar saat edit — '.($employee->exit_reason_label ?? $employee->exit_reason).'.',
                    $exitDate,
                    ['exit_reason' => $employee->exit_reason],
                );
            }
        });

        if ($request->hasFile('photo') && $oldPhotoPath && $oldPhotoPath !== $employee->photo_path) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()
            ->route('employees.index')
            ->with('status', $isExitViaEdit
                ? 'Data karyawan diperbarui dan status akhir (keluar) berhasil diproses.'
                : 'Data karyawan berhasil diperbarui.');
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

            $employee->markAsExited(
                $request->validated('exit_reason'),
                $exitDate,
                $request->validated('exit_notes'),
            );

            $employee->recordEvent(
                'exited',
                'Diproses keluar — '.($employee->exit_reason_label ?? $employee->exit_reason).'.',
                $exitDate,
                ['exit_reason' => $employee->exit_reason],
            );
        });

        return redirect()
            ->route('employees.index')
            ->with('status', 'Status akhir karyawan berhasil diperbarui.');
    }

    public function renewContract(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'contract_number' => ['required', 'string', 'max:100', 'unique:employee_contracts,contract_number'],
            'contract_type' => ['required', 'string', Rule::in(['PKWT', 'PKWTT', 'Probation', 'Internship'])],
            'start_date' => ['required', 'date'],
            'end_date' => [Rule::requiredIf(fn () => $request->input('contract_type') !== 'PKWTT'), 'nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'end_date.required' => 'Tanggal selesai kontrak wajib diisi untuk jenis kontrak selain PKWTT.',
        ]);

        $wasInactive = $employee->isInactive();

        DB::transaction(function () use ($validated, $employee, $wasInactive) {
            $employee->loadMissing('currentContract', 'user');

            // Renewing a contract for someone who has left = rehire: reactivate them
            // and clear the exit details (the timeline keeps the history).
            if ($wasInactive) {
                $employee->forceFill([
                    'employment_status' => 'active',
                    'exit_reason' => null,
                    'exit_date' => null,
                    'exit_notes' => null,
                ])->save();

                if ($employee->user) {
                    $employee->user->forceFill(['is_active' => true])->save();
                }
            }

            $previous = $employee->currentContract;

            if ($previous) {
                $previous->forceFill(['status' => 'renewed'])->save();
            }

            $new = $employee->contracts()->create([
                'contract_number' => $validated['contract_number'],
                'contract_type' => $validated['contract_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($wasInactive) {
                $employee->recordEvent(
                    'reactivated',
                    "Diaktifkan kembali dengan kontrak baru {$new->contract_number}.",
                    $new->start_date,
                    ['to' => $new->contract_number],
                );
            } else {
                $employee->recordEvent(
                    'contract_renewed',
                    $previous
                        ? "Kontrak diperpanjang dari {$previous->contract_number} ke {$new->contract_number}."
                        : "Kontrak {$new->contract_number} dibuat.",
                    $new->start_date,
                    ['from' => $previous?->contract_number, 'to' => $new->contract_number],
                );
            }
        });

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', $wasInactive
                ? 'Karyawan berhasil diaktifkan kembali dengan kontrak baru.'
                : 'Kontrak berhasil diperpanjang.');
    }

    /**
     * Auto-deactivate employees with an expired contract, at most once per day so it
     * runs during normal usage even when no scheduler/cron is configured.
     */
    private function deactivateExpiredContractsDaily(): void
    {
        $key = 'employees:auto-deactivate:'.now()->toDateString();

        if (! Cache::add($key, true, now()->endOfDay())) {
            return;
        }

        try {
            app(DeactivateExpiredContracts::class)->run();
        } catch (\Throwable $exception) {
            report($exception);
        }
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
            'statuses' => Employee::workStatusLabels(),
            'exitReasons' => Employee::exitReasonLabels(),
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
            'contractStatuses' => EmployeeContract::statusLabels(),
            'closingContractStatuses' => EmployeeContract::closingStatuses(),
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
