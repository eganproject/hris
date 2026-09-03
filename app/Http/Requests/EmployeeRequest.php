<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\SchedulePattern;
use App\Models\User;
use App\Support\UploadMessages;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Backward-compat: payload lama/impor yang masih mengirim satu department_id
     * dilipat menjadi department_ids, sehingga model divisi-jamak tetap berjalan.
     */
    protected function prepareForValidation(): void
    {
        if (empty($this->input('department_ids')) && $this->filled('department_id')) {
            $this->merge(['department_ids' => [$this->integer('department_id')]]);
        }

        // Modal "Proses Karyawan Keluar" hidup di dalam formulir yang sama dan hanya
        // disembunyikan, jadi exit_reason/exit_date/exit_notes ikut terkirim pada
        // SETIAP penyimpanan — termasuk saat statusnya tetap Aktif. Dibiarkan begitu,
        // tanggal keluar bawaannya (hari ini) tetap diadu dengan tanggal bergabung,
        // sehingga karyawan yang tanggal bergabungnya masih di depan ditolak dengan
        // galat "tanggal keluar" — dan halamannya kembali dengan modal keluar
        // terbuka, seolah-olah orangnya sedang diproses keluar. Selama proses keluar
        // tidak benar-benar dijalankan, kolomnya dibuang.
        if (! $this->isProcessingExit()) {
            $this->merge(['exit_reason' => null, 'exit_date' => null, 'exit_notes' => null]);
        }
    }

    /**
     * Penyimpanan ini benar-benar memproses karyawan keluar: statusnya diubah menjadi
     * "Nonaktif" dari keadaan yang belum nonaktif (atau karyawan baru yang langsung
     * dicatat nonaktif). Hanya pada keadaan itu alasan & tanggal keluar wajib diisi.
     */
    private function isProcessingExit(): bool
    {
        $employee = $this->route('employee');

        return $this->input('employment_status') === 'inactive'
            && (! $employee || ! $employee->isInactive());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employeeId = $employee?->id;
        $userId = $employee?->user_id;
        $requiresLoginPassword = $this->isMethod('post') && $this->filled('email') && ! $userId;

        // Setting the status to "Nonaktif" (from a not-already-inactive state, or on
        // a brand-new employee) triggers the exit flow, so the reason & date become
        // required and are processed together with the save.
        $isClosingExit = $this->isProcessingExit();

        // Reactivating from the edit form starts a fresh contract (see update()), so
        // its number must be new. A plain edit keeps working on the contract already
        // stored, so that same number must not be flagged as taken.
        $isReactivating = $employee && $employee->isInactive() && $this->input('employment_status') === 'active';
        $contractId = $isReactivating ? null : $employee?->editableContract()?->id;
        $exitJoinDate = $employee?->join_date?->format('Y-m-d') ?: $this->input('join_date');

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            // Divisi karyawan — semua setara, minimal satu. Tidak ada "divisi utama".
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'job_position_id' => ['required', 'integer', 'exists:job_positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id', Rule::notIn([$employeeId])],
            'machine_pins' => ['nullable', 'array'],
            'machine_pins.*.device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'machine_pins.*.machine_user_id' => ['nullable', 'string', 'max:50'],
            // employee_number is not accepted here: the system generates it (COK…).
            'photo' =>['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=300,max_width=3000,max_height=3000'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employeeId),
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'join_date' => ['required', 'date'],
            'employment_status' => ['required', 'string', Rule::in(array_keys(Employee::employmentStatusLabels()))],
            'follows_office_hours' => ['nullable', 'boolean'],
            // Hanya pola yang didaftarkan sebagai pola jam kantor di Pengaturan yang
            // boleh dipilih. Kosong = ikut pola default global.
            'office_pattern_id' => ['nullable', 'integer', $this->officePatternRule()],
            'address' => ['nullable', 'string', 'max:1000'],
            'contract_number' => ['required', 'string', 'max:100', Rule::unique('employee_contracts', 'contract_number')->ignore($contractId)],
            'contract_type' => ['required', 'string', Rule::in(['PKWT', 'PKWTT', 'Probation', 'Internship'])],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => [
                Rule::requiredIf(fn () => $this->input('contract_type') !== 'PKWTT'),
                'nullable',
                'date',
                'after_or_equal:contract_start_date',
            ],
            'contract_status' => ['required', 'string', Rule::in(array_keys(EmployeeContract::statusLabels()))],
            'contract_notes' => ['nullable', 'string', 'max:1000'],
            // Dokumen kontrak yang sudah ditandatangani. Hanya PDF: yang diarsipkan
            // di sini adalah kontrak final, bukan foto tangkapan layar.
            'contract_document' => ['nullable', 'file', 'mimes:pdf', 'max:'.(EmployeeContract::DOCUMENT_MAX_MB * 1024)],
            'contract_document_remove' => ['sometimes', 'boolean'],
            'exit_reason' => [Rule::requiredIf($isClosingExit), 'nullable', 'string', Rule::in(array_keys(Employee::exitReasonLabels()))],
            'exit_date' => array_filter([
                $isClosingExit ? 'required' : 'nullable',
                'date',
                'before_or_equal:today',
                $exitJoinDate ? 'after_or_equal:'.$exitJoinDate : null,
            ]),
            'exit_notes' => ['nullable', 'string', 'max:1000'],
            'login_password' => [Rule::requiredIf($requiresLoginPassword), 'nullable', 'string', 'min:8'],
            'login_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'leave_balance' => ['nullable', 'array'],
            'leave_balance.*' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    /**
     * Nama kolom dalam bahasa Indonesia, supaya pesan galat bawaan Laravel menyebut
     * kolom yang sama dengan labelnya di formulir.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'lokasi kerja',
            'department_ids' => 'divisi',
            'job_position_id' => 'jabatan',
            'manager_id' => 'atasan langsung',
            'photo' => 'foto karyawan',
            'full_name' => 'nama lengkap',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'identity_number' => 'nomor identitas',
            'birth_date' => 'tanggal lahir',
            'join_date' => 'tanggal bergabung',
            'employment_status' => 'status kepegawaian',
            'office_pattern_id' => 'pola jam kantor',
            'address' => 'alamat',
            'contract_number' => 'nomor kontrak',
            'contract_type' => 'jenis kontrak',
            'contract_start_date' => 'tanggal mulai kontrak',
            'contract_end_date' => 'tanggal selesai kontrak',
            'contract_status' => 'status kontrak',
            'contract_notes' => 'catatan kontrak',
            'contract_document' => 'dokumen kontrak',
            'exit_reason' => 'alasan keluar',
            'exit_date' => 'tanggal keluar',
            'exit_notes' => 'catatan keluar',
            'login_password' => 'password login',
            'login_role_id' => 'role login',
            'machine_pins' => 'PIN mesin absensi',
        ];
    }

    /**
     * Pola jam kantor yang boleh dipilih: kandidat yang terdaftar di Pengaturan —
     * ATAU pola yang memang sudah dipakai karyawan ini.
     *
     * Pengecualian kedua itu bukan kelonggaran, melainkan keharusan: sebuah pola bisa
     * dicabut dari daftar kandidat atau diarsipkan setelah orangnya terlanjur
     * memakainya, dan tanpa ini menyimpan formulirnya sama sekali — bahkan cuma untuk
     * membetulkan nomor telepon — akan ditolak sampai HR memindahkan orangnya.
     */
    private function officePatternRule(): Closure
    {
        $current = $this->route('employee')?->office_pattern_id;

        return function (string $attribute, mixed $value, Closure $fail) use ($current): void {
            if ($current && (int) $value === (int) $current) {
                return;
            }

            if (! SchedulePattern::query()->officeCandidates()->whereKey($value)->exists()) {
                $fail('Pola jam kantor yang dipilih tidak tersedia. Daftarkan dulu polanya di menu Pengaturan.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'contract_end_date.required' => 'Tanggal selesai kontrak wajib diisi untuk jenis kontrak selain PKWTT.',
            ...UploadMessages::pdf('contract_document', EmployeeContract::DOCUMENT_MAX_MB, 'Dokumen kontrak'),
            ...UploadMessages::photo('photo', 2, 'Foto karyawan', dimensions: 'Resolusi foto karyawan minimal 300x300 px dan maksimal 3000x3000 px.'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->route('employee')?->user_id && ! $this->filled('email')) {
                    $validator->errors()->add('email', 'Email karyawan wajib diisi karena akun login sudah aktif.');

                    return;
                }

                // Validate the fingerprint PIN rows: no duplicate machine within the
                // form, and no PIN already used by another employee on that machine.
                $currentId = $this->route('employee')?->id;
                $seenDevices = [];
                $validPins = 0;

                foreach ((array) $this->input('machine_pins', []) as $index => $row) {
                    $pin = trim((string) ($row['machine_user_id'] ?? ''));

                    if ($pin === '') {
                        continue; // empty rows are ignored on save
                    }

                    $validPins++;
                    $deviceId = ($row['device_id'] ?? null) ?: null;
                    $deviceKey = (int) $deviceId;

                    if (in_array($deviceKey, $seenDevices, true)) {
                        $validator->errors()->add("machine_pins.$index.device_id", 'Mesin ini sudah dipilih di baris lain.');

                        continue;
                    }
                    $seenDevices[] = $deviceKey;

                    $conflict = DB::table('employee_devices')
                        ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId), fn ($q) => $q->whereNull('device_id'))
                        ->where('machine_user_id', $pin)
                        ->when($currentId, fn ($q) => $q->where('employee_id', '!=', $currentId))
                        ->exists();

                    if ($conflict) {
                        $validator->errors()->add("machine_pins.$index.machine_user_id", 'PIN ini sudah dipakai karyawan lain pada mesin tersebut.');
                    }
                }

                if ($validPins === 0) {
                    $validator->errors()->add('machine_pins', 'Minimal satu PIN mesin absensi wajib diisi.');
                }

                $branchId = $this->integer('branch_id');
                $jobPositionId = $this->integer('job_position_id');

                // Semua divisi karyawan (setara, minimal satu).
                $divisionIds = collect($this->input('department_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique();

                // The placement must stay inside the user's data scope; the pickers
                // already hide the rest, but a hand-crafted POST must fail too.
                $user = $this->user();
                $allowedDepartments = $user->seesAllData(User::SCOPE_BYPASS_EMPLOYEES) ? [] : $user->accessDepartmentIds();

                if (! $user->seesAllData(User::SCOPE_BYPASS_EMPLOYEES)) {
                    $allowedBranches = $user->accessBranchIds();

                    if ($allowedBranches !== [] && ! in_array($branchId, $allowedBranches, true)) {
                        $validator->errors()->add('branch_id', 'Lokasi kerja tersebut berada di luar cakupan akses Anda.');
                    }
                }

                // Setiap divisi harus tersedia pada lokasi kerja dan dalam cakupan user.
                $availableAtBranch = DB::table('branch_department')
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->pluck('department_id')
                    ->all();

                foreach ($divisionIds as $id) {
                    if (! in_array($id, $availableAtBranch, true)) {
                        $validator->errors()->add('department_ids', 'Ada divisi yang tidak tersedia pada lokasi kerja yang dipilih.');
                        break;
                    }

                    if ($allowedDepartments !== [] && ! in_array($id, $allowedDepartments, true)) {
                        $validator->errors()->add('department_ids', 'Ada divisi yang berada di luar cakupan akses Anda.');
                        break;
                    }
                }

                // Jabatan boleh berasal dari divisi mana pun yang dimiliki karyawan.
                $divisionIds = $divisionIds->all();

                $jobPositionAvailable = DB::table('department_job_position')
                    ->join('job_positions', 'job_positions.id', '=', 'department_job_position.job_position_id')
                    ->whereIn('department_job_position.department_id', $divisionIds)
                    ->where('department_job_position.job_position_id', $jobPositionId)
                    ->where('department_job_position.is_active', true)
                    ->where('job_positions.is_active', true)
                    ->exists();

                if (! $jobPositionAvailable) {
                    $validator->errors()->add('job_position_id', 'Jabatan tidak tersedia di divisi mana pun yang dipilih untuk karyawan ini.');
                }
            },
        ];
    }
}
