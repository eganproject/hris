<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateExpiredContracts;
use App\Exports\EmployeesExport;
use App\Exports\EmployeeTemplateExport;
use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ResignEmployeeRequest;
use App\Imports\EmployeesImport;
use App\Support\EmployeeImportErrorReport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobPosition;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\PunchIngestionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->deactivateExpiredContractsDaily();

        $filters = $request->only(['branch_id', 'department_id', 'status', 'exit_reason', 'search', 'contract']);
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        // Shared filtering so the summary cards always reflect the same dataset the
        // table shows. Applied to a fresh query for each card count.
        $applyFilters = fn (Builder $query): Builder => $query
            ->byBranch($filters['branch_id'] ?? null)
            ->byDepartment($filters['department_id'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('employment_status', $status))
            ->when($filters['exit_reason'] ?? null, fn ($query, string $exitReason) => $query->where('exit_reason', $exitReason))
            ->when(($filters['contract'] ?? null) === 'expiring', fn ($query) => $query->whereHas('contracts', fn ($c) => $c->expiringWithin(30)))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $employees = $applyFilters(Employee::query()->with(['branch', 'department', 'jobPosition', 'currentContract']))
            ->latest('join_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'branches' => Branch::query()->where('is_active', true)->orderBy('city')->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'summary' => [
                'total' => $applyFilters(Employee::query())->count(),
                'active' => $applyFilters(Employee::query())->active()->count(),
                'inactive' => $applyFilters(Employee::query())->where('employment_status', 'inactive')->count(),
                'locations' => (int) $applyFilters(Employee::query())->whereNotNull('branch_id')->distinct()->count('branch_id'),
                'expiring_contracts' => EmployeeContract::query()->expiringWithin(30)
                    ->whereIn('employee_id', $applyFilters(Employee::query())->select('id'))->count(),
            ],
            'filters' => $filters,
            'perPage' => $perPage,
            'statuses' => Employee::employmentStatusLabels(),
            'exitReasons' => Employee::exitReasonLabels(),
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
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
            $this->syncMachinePins($employee, $request->validated('machine_pins', []));
            $this->syncLeaveBalances($employee, $request->input('leave_balance', []));

            $employee->recordEvent('joined', 'Bergabung sebagai karyawan.', $employee->join_date);
            $employee->recordEvent(
                'contract_created',
                "Kontrak {$contract->contract_number} ({$contract->contract_type}) dibuat.",
                $contract->start_date,
                ['contract_number' => $contract->contract_number],
            );

            // Created straight as Nonaktif: run the exit flow so the reason/date are
            // recorded and the contract is closed, consistent with the edit form.
            if ($request->validated('employment_status') === 'inactive') {
                $exitDate = $request->date('exit_date')->startOfDay();

                $employee->markAsExited(
                    $request->validated('exit_reason'),
                    $exitDate,
                    $request->validated('exit_notes'),
                );

                $employee->recordEvent(
                    'exited',
                    'Dibuat dengan status keluar — '.($employee->exit_reason_label ?? $employee->exit_reason).'.',
                    $exitDate,
                    ['exit_reason' => $employee->exit_reason],
                );
            }
        });

        return redirect()
            ->route('employees.index')
            ->with('status', 'Data karyawan berhasil dibuat.');
    }

    /**
     * Download the blank .xlsx import template (data sheet + instructions sheet).
     */
    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new EmployeeTemplateExport, 'template-import-karyawan.xlsx');
    }

    /**
     * Export the employee list (honouring the current filters) to .xlsx.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['branch_id', 'department_id', 'status', 'exit_reason', 'search']);

        return Excel::download(
            new EmployeesExport($filters),
            'data-karyawan-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Import employees from the filled-in .xlsx/.csv template. Validation is
     * all-or-nothing: if any row is invalid, nothing is saved and the row-level
     * errors are flashed back so the import modal can show them.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['file' => 'file Excel']);

        $import = new EmployeesImport;

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('import_errors', ['Gagal membaca file. Pastikan file sesuai template. ('.$exception->getMessage().')']);
        }

        if ($import->errors() !== []) {
            $token = $this->storeImportErrorReport($request->file('file'), $import->rowErrors());

            return back()
                ->with('import_errors', $import->errors())
                ->with('import_error_token', $token);
        }

        return redirect()
            ->route('employees.index')
            ->with('status', "Berhasil mengimpor {$import->imported()} data karyawan.");
    }

    /**
     * Download a copy of the just-uploaded file annotated with the import errors:
     * offending cells highlighted, a per-row "Kesalahan" column, plus a sheet
     * listing every problem and the column it belongs to.
     */
    public function importErrors(string $token): BinaryFileResponse
    {
        abort_unless(Str::isUuid($token), 404);

        $dir = 'import-error-reports';
        $payloadPath = "{$dir}/{$token}.json";

        abort_unless(Storage::exists($payloadPath), 404);

        /** @var array{source: string, original_name: string, errors: list<array{row: ?int, column: ?string, message: string}>} $payload */
        $payload = json_decode(Storage::get($payloadPath), true);

        abort_unless(is_array($payload) && Storage::exists($payload['source']), 404);

        $name = 'kesalahan-import-'.pathinfo($payload['original_name'], PATHINFO_FILENAME).'.xlsx';

        return EmployeeImportErrorReport::download(
            Storage::path($payload['source']),
            $payload['errors'],
            $name,
        );
    }

    /**
     * Persist the uploaded file plus its structured errors so they can be
     * rebuilt into a downloadable report on a later request. Returns the token
     * the download route resolves. Stale reports (older than a day) are pruned.
     *
     * @param  list<array{row: ?int, column: ?string, message: string}>  $errors
     */
    private function storeImportErrorReport(\Illuminate\Http\UploadedFile $file, array $errors): string
    {
        $dir = 'import-error-reports';
        $token = (string) Str::uuid();

        foreach (Storage::files($dir) as $old) {
            if (Storage::lastModified($old) < now()->subDay()->getTimestamp()) {
                Storage::delete($old);
            }
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $source = "{$dir}/{$token}.{$extension}";
        Storage::put($source, file_get_contents($file->getRealPath()));

        Storage::put("{$dir}/{$token}.json", json_encode([
            'source' => $source,
            'original_name' => $file->getClientOriginalName(),
            'errors' => $errors,
        ]));

        return $token;
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
            'deviceMappings.device.branch',
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
        ]);
    }

    public function edit(Employee $employee): View
    {
        $employee->load('currentContract', 'user.roles', 'deviceMappings.device', 'leaveBalances');

        return view('employees.edit', [
            'employee' => $employee,
            ...$this->formOptions(),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $oldPhotoPath = $employee->photo_path;
        $wasActive = ! $employee->isInactive();
        $desiredStatus = $request->validated('employment_status');

        // Exit and reactivation are now driven by the "Status Kepegawaian" field:
        // Aktif → Nonaktif runs the exit flow; Nonaktif → Aktif reactivates.
        $isExitViaEdit = $wasActive && $desiredStatus === 'inactive';
        $isReactivateViaEdit = ! $wasActive && $desiredStatus === 'active';

        DB::transaction(function () use ($request, $employee, $isExitViaEdit, $isReactivateViaEdit) {
            $employee->update($this->employeePayload($request));

            $contract = $employee->currentContract ?: $employee->contracts()->make();
            $contract->fill($this->contractPayload($request));
            $contract->save();

            $this->syncLoginAccount($request, $employee);
            $this->syncMachinePins($employee, $request->validated('machine_pins', []));
            $this->syncLeaveBalances($employee, $request->input('leave_balance', []));

            // Status set to Nonaktif: process the exit here (record reason/date and
            // close the current contract) so the user stays on the edit form.
            if ($isExitViaEdit) {
                $exitDate = $request->date('exit_date')->startOfDay();

                $employee->markAsExited(
                    $request->validated('exit_reason'),
                    $exitDate,
                    $request->validated('exit_notes'),
                );

                $employee->recordEvent(
                    'exited',
                    'Diproses keluar saat edit — '.($employee->exit_reason_label ?? $employee->exit_reason).'.',
                    $exitDate,
                    ['exit_reason' => $employee->exit_reason],
                );
            } elseif ($isReactivateViaEdit) {
                // Ensure the reactivated employee has a valid active contract.
                $contract->forceFill(['status' => 'active'])->save();

                $employee->reactivate();

                $employee->recordEvent(
                    'reactivated',
                    "Diaktifkan kembali saat edit dengan kontrak {$contract->contract_number}.",
                    now(),
                    ['contract_number' => $contract->contract_number],
                );
            }
        });

        if ($request->hasFile('photo') && $oldPhotoPath && $oldPhotoPath !== $employee->photo_path) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()
            ->route('employees.index')
            ->with('status', match (true) {
                $isExitViaEdit => 'Data karyawan diperbarui dan status akhir (keluar) berhasil diproses.',
                $isReactivateViaEdit => 'Data karyawan diperbarui dan karyawan berhasil diaktifkan kembali.',
                default => 'Data karyawan berhasil diperbarui.',
            });
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
        $wasInactive = $employee->isInactive();

        $validator = Validator::make($request->all(), [
            'contract_number' => ['required', 'string', 'max:100', 'unique:employee_contracts,contract_number'],
            'contract_type' => ['required', 'string', Rule::in(['PKWT', 'PKWTT', 'Probation', 'Internship'])],
            'start_date' => ['required', 'date'],
            'end_date' => [Rule::requiredIf(fn () => $request->input('contract_type') !== 'PKWTT'), 'nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'end_date.required' => 'Tanggal selesai kontrak wajib diisi untuk jenis kontrak selain PKWTT.',
        ]);

        if ($validator->fails()) {
            // Flash the employee so the list page can re-open the right modal with errors.
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('renew_employee', [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'mode' => $wasInactive ? 'reactivate' : 'renew',
                ]);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $employee, $wasInactive) {
            $employee->loadMissing('currentContract', 'user');

            // Renewing a contract for someone who has left = rehire: reactivate them
            // (exit details are cleared; the timeline keeps the history).
            if ($wasInactive) {
                $employee->reactivate();
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
            ->to($request->boolean('from_list') ? route('employees.index') : route('employees.show', $employee))
            ->with('status', $wasInactive
                ? 'Karyawan berhasil diaktifkan kembali dengan kontrak baru.'
                : 'Kontrak berhasil diperpanjang.');
    }

    /**
     * Bulk "proses keluar". The list page walks the checklisted employees one by one
     * (a per-employee wizard) and posts an entry per employee, so each may have its
     * own reason/date/notes. Already-exited rows (or an exit date before the join
     * date) are skipped.
     */
    public function bulkExit(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'entries.*.exit_reason' => ['required', 'string', Rule::in(array_keys(Employee::exitReasonLabels()))],
            'entries.*.exit_date' => ['required', 'date', 'before_or_equal:today'],
            'entries.*.exit_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->with('bulk_error', $validator->errors()->first());
        }

        $entries = $validator->validated()['entries'];
        $employees = Employee::query()->whereIn('id', collect($entries)->pluck('employee_id'))->get()->keyBy('id');
        $processed = 0;
        $skipped = 0;

        DB::transaction(function () use ($entries, $employees, &$processed, &$skipped) {
            foreach ($entries as $entry) {
                $employee = $employees->get((int) $entry['employee_id']);
                $exitDate = \Illuminate\Support\Carbon::parse($entry['exit_date'])->startOfDay();

                if (! $employee || $employee->isInactive() || ($employee->join_date && $exitDate->lt($employee->join_date))) {
                    $skipped++;

                    continue;
                }

                $employee->loadMissing('currentContract', 'user');
                $employee->markAsExited($entry['exit_reason'], $exitDate, $entry['exit_notes'] ?? null);

                $employee->recordEvent(
                    'exited',
                    'Diproses keluar (massal) — '.($employee->exit_reason_label ?? $employee->exit_reason).'.',
                    $exitDate,
                    ['exit_reason' => $employee->exit_reason],
                );

                $processed++;
            }
        });

        return redirect()
            ->route('employees.index')
            ->with('status', "Proses keluar massal: {$processed} karyawan diproses"
                .($skipped > 0 ? ", {$skipped} dilewati (sudah keluar / tanggal tidak valid)" : '').'.');
    }

    /**
     * Bulk "perpanjang kontrak", filled per employee by the list-page wizard. Each
     * entry carries its own (unique) contract number and terms; uniqueness is checked
     * across the batch and against existing contracts before anything is saved.
     * Employees who have left are reactivated (rehired), mirroring the single-row flow.
     */
    public function bulkRenew(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'entries.*.contract_number' => ['required', 'string', 'max:100'],
            'entries.*.contract_type' => ['required', 'string', Rule::in(['PKWT', 'PKWTT', 'Probation', 'Internship'])],
            'entries.*.start_date' => ['required', 'date'],
            'entries.*.end_date' => ['nullable', 'date'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->with('bulk_error', $validator->errors()->first());
        }

        $entries = $validator->validated()['entries'];

        // Per-entry business rules (end date requirement/order).
        foreach ($entries as $entry) {
            if ($entry['contract_type'] !== 'PKWTT' && empty($entry['end_date'])) {
                return back()->with('bulk_error', "Kontrak \"{$entry['contract_number']}\": tanggal selesai wajib diisi untuk jenis selain PKWTT.");
            }

            if (! empty($entry['end_date']) && $entry['end_date'] < $entry['start_date']) {
                return back()->with('bulk_error', "Kontrak \"{$entry['contract_number']}\": tanggal selesai tidak boleh sebelum tanggal mulai.");
            }
        }

        // Contract numbers must be unique within the batch and against existing rows.
        $numbers = collect($entries)->pluck('contract_number');
        $clash = $numbers->duplicates()->first()
            ?? EmployeeContract::query()->whereIn('contract_number', $numbers->all())->value('contract_number');

        if ($clash) {
            return back()->with('bulk_error', "Nomor kontrak \"{$clash}\" duplikat atau sudah dipakai. Pastikan setiap karyawan memakai nomor unik.");
        }

        $employees = Employee::query()->whereIn('id', collect($entries)->pluck('employee_id'))->get()->keyBy('id');
        $processed = 0;

        DB::transaction(function () use ($entries, $employees, &$processed) {
            foreach ($entries as $entry) {
                $employee = $employees->get((int) $entry['employee_id']);

                if (! $employee) {
                    continue;
                }

                $wasInactive = $employee->isInactive();
                $employee->loadMissing('currentContract');

                if ($wasInactive) {
                    $employee->reactivate();
                }

                $previous = $employee->currentContract;

                if ($previous) {
                    $previous->forceFill(['status' => 'renewed'])->save();
                }

                $type = $entry['contract_type'];
                $new = $employee->contracts()->create([
                    'contract_number' => $entry['contract_number'],
                    'contract_type' => $type,
                    'start_date' => $entry['start_date'],
                    'end_date' => $type === 'PKWTT' ? null : ($entry['end_date'] ?? null),
                    'status' => 'active',
                    'notes' => $entry['notes'] ?? null,
                ]);

                $employee->recordEvent(
                    $wasInactive ? 'reactivated' : 'contract_renewed',
                    $wasInactive
                        ? "Diaktifkan kembali dengan kontrak baru {$new->contract_number} (massal)."
                        : ($previous
                            ? "Kontrak diperpanjang dari {$previous->contract_number} ke {$new->contract_number} (massal)."
                            : "Kontrak {$new->contract_number} dibuat (massal)."),
                    $new->start_date,
                    ['from' => $previous?->contract_number, 'to' => $new->contract_number],
                );

                $processed++;
            }
        });

        return redirect()
            ->route('employees.index')
            ->with('status', "Perpanjang kontrak massal: {$processed} karyawan diproses.");
    }

    /**
     * Bulk delete the checklisted employees.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ], [], ['employee_ids' => 'karyawan terpilih']);

        if ($validator->fails()) {
            return back()->with('bulk_error', $validator->errors()->first());
        }

        $employees = Employee::query()->whereIn('id', $validator->validated()['employee_ids'])->get();
        $count = 0;

        DB::transaction(function () use ($employees, &$count) {
            foreach ($employees as $employee) {
                $employee->delete();
                $count++;
            }
        });

        return redirect()
            ->route('employees.index')
            ->with('status', "{$count} karyawan berhasil dihapus.");
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
            'leaveTypes' => LeaveType::query()->where('is_active', true)->where('counts_against_balance', true)->orderBy('name')->get(),
            'managers' => Employee::query()->active()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'devices' => Device::query()->with('branch')->orderBy('name')->get(),
            'statuses' => Employee::employmentStatusLabels(),
            'exitReasons' => Employee::exitReasonLabels(),
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
            'contractStatuses' => EmployeeContract::statusLabels(),
            'closingContractStatuses' => EmployeeContract::closingStatuses(),
        ];
    }

    /**
     * Sync the employee's fingerprint-machine PIN mappings from the form. Each row is
     * a device (null = any machine) + PIN. Assigning a PIN also back-fills punches
     * already received under it (see PunchIngestionService); removed rows are deleted.
     *
     * @param  array<int, array{device_id?: mixed, machine_user_id?: mixed}>  $rows
     */
    private function syncMachinePins(Employee $employee, array $rows): void
    {
        $ingestion = app(PunchIngestionService::class);
        $kept = [];

        foreach ($rows as $row) {
            $pin = trim((string) ($row['machine_user_id'] ?? ''));

            if ($pin === '') {
                continue;
            }

            $deviceId = ($row['device_id'] ?? null) ?: null;
            $device = $deviceId ? Device::find($deviceId) : null;

            $ingestion->assignPin($employee, $device, $pin);
            $kept[] = (int) $deviceId.'|'.$pin;
        }

        // Drop mappings the user removed from the form.
        foreach ($employee->deviceMappings()->get() as $mapping) {
            if (! in_array((int) $mapping->device_id.'|'.$mapping->machine_user_id, $kept, true)) {
                $mapping->delete();
            }
        }
    }

    /**
     * Store this year's leave quota per leave type. Mirrors the bulk Leave Balances
     * screen: an explicit row is kept only when it differs from the leave type's
     * default; matching the default drops any override so the default is inherited.
     *
     * @param  array<int|string, mixed>  $balances  leaveTypeId => quota
     */
    private function syncLeaveBalances(Employee $employee, array $balances): void
    {
        if ($balances === []) {
            return;
        }

        $year = (int) now()->year;

        $types = LeaveType::query()
            ->where('counts_against_balance', true)
            ->get()
            ->keyBy('id');

        foreach ($balances as $typeId => $value) {
            $type = $types->get((int) $typeId);

            if (! $type) {
                continue;
            }

            $default = (int) ($type->default_quota_days ?? 0);
            $entered = ($value === '' || $value === null) ? $default : (int) $value;
            $entered = max(0, min(365, $entered));

            if ($entered === $default) {
                $employee->leaveBalances()
                    ->where(['leave_type_id' => $type->id, 'year' => $year])
                    ->delete();

                continue;
            }

            $employee->leaveBalances()->updateOrCreate(
                ['leave_type_id' => $type->id, 'year' => $year],
                ['quota_days' => $entered],
            );
        }
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
            'manager_id',
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

        $user ??= new User;

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
