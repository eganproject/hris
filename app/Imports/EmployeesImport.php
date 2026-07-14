<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobPosition;
use App\Models\User;
use App\Services\PunchIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports employees (with their placement, first contract and — optionally — an
 * attendance-machine PIN) from the accompanying Excel template. Validation is
 * all-or-nothing: every row is checked first and — only if the whole file is
 * clean — the rows are persisted. Otherwise no employee is created and the
 * collected, human-readable errors are surfaced to the user, one per problem,
 * prefixed with the row number.
 *
 * Lokasi Kerja / Divisi / Jabatan that do not exist yet are created on persist and
 * linked together automatically. A PIN is optional, but when given it must name a
 * registered machine (by serial number) and be unique on that machine. A login
 * account is created when both Email and Password Login are provided.
 */
class EmployeesImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /**
     * Structured problems, one per issue: the offending row (null for
     * file-level problems), the human-readable column header it belongs to
     * (null when the problem is not tied to a single column) and the message.
     *
     * @var list<array{row: ?int, column: ?string, message: string}>
     */
    private array $rowErrors = [];

    private int $imported = 0;

    /**
     * The importer's data scope, lowercased. A null list means "no limit on this
     * axis"; a list means every row must name one of those, and nothing new may be
     * created there — an HR cabang cannot import people into another location.
     *
     * @param  list<string>|null  $allowedBranches
     * @param  list<string>|null  $allowedDepartments
     */
    public function __construct(
        private readonly ?array $allowedBranches = null,
        private readonly ?array $allowedDepartments = null,
    ) {}

    /**
     * Column metadata, shared with the template/guide export so the header row,
     * the instructions sheet and this importer never drift apart.
     *
     * @return list<array{key: string, header: string, required: bool, example: string, desc: string}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'nomor_karyawan', 'header' => 'Nomor Karyawan', 'required' => false, 'example' => 'COK0724-SBYOFC010012', 'desc' => 'Dibuat otomatis oleh sistem saat karyawan disimpan (format COK[bulan][tahun bergabung]-[kode lokasi][id]). Kolom ini hanya untuk data hasil ekspor — isinya diabaikan saat impor.'],
            ['key' => 'nama_lengkap', 'header' => 'Nama Lengkap', 'required' => true, 'example' => 'Budi Santoso', 'desc' => 'Nama lengkap karyawan.'],
            ['key' => 'email', 'header' => 'Email', 'required' => false, 'example' => 'budi@contoh.com', 'desc' => 'Opsional. Jika diisi harus unik. Akun login TIDAK dibuat otomatis dari import.'],
            ['key' => 'telepon', 'header' => 'Telepon', 'required' => false, 'example' => '08123456789', 'desc' => 'Opsional.'],
            ['key' => 'nik', 'header' => 'NIK', 'required' => false, 'example' => '3201xxxxxxxxxxxx', 'desc' => 'Nomor identitas (KTP). Opsional.'],
            ['key' => 'tanggal_lahir', 'header' => 'Tanggal Lahir', 'required' => false, 'example' => '1995-04-17', 'desc' => 'Format YYYY-MM-DD. Opsional, harus sebelum hari ini.'],
            ['key' => 'tanggal_bergabung', 'header' => 'Tanggal Bergabung', 'required' => true, 'example' => '2024-01-05', 'desc' => 'Format YYYY-MM-DD. Wajib.'],
            ['key' => 'status_kepegawaian', 'header' => 'Status Kepegawaian', 'required' => true, 'example' => 'Aktif', 'desc' => 'Salah satu: Aktif, Nonaktif. Umumnya "Aktif" untuk karyawan baru.'],
            ['key' => 'alamat', 'header' => 'Alamat', 'required' => false, 'example' => 'Jl. Melati No. 1', 'desc' => 'Opsional.'],
            ['key' => 'lokasi_kerja', 'header' => 'Lokasi Kerja', 'required' => true, 'example' => 'Kantor Pusat', 'desc' => 'Nama lokasi/cabang; harus sudah terdaftar dan aktif.'],
            ['key' => 'divisi', 'header' => 'Divisi', 'required' => true, 'example' => 'Operasional', 'desc' => 'Nama divisi; harus tersedia pada lokasi kerja tersebut.'],
            ['key' => 'jabatan', 'header' => 'Jabatan', 'required' => true, 'example' => 'Staf', 'desc' => 'Nama jabatan; harus sesuai dengan divisi.'],
            ['key' => 'nomor_nama_atasan', 'header' => 'Nomor / Nama Atasan', 'required' => false, 'example' => 'Dewi Anggraeni', 'desc' => 'Opsional. Atasan langsung: isi kode karyawan (mis. COK0724-SBYOFC010012) untuk karyawan yang sudah terdaftar, atau nama lengkapnya — termasuk bila atasan tersebut baru dibuat lewat baris lain di file ini.'],
            ['key' => 'nomor_kontrak', 'header' => 'Nomor Kontrak', 'required' => true, 'example' => 'KTR-2024-001', 'desc' => 'Nomor kontrak unik. Wajib.'],
            ['key' => 'jenis_kontrak', 'header' => 'Jenis Kontrak', 'required' => true, 'example' => 'PKWT', 'desc' => 'Salah satu: PKWT, PKWTT, Probation, Internship.'],
            ['key' => 'tanggal_mulai_kontrak', 'header' => 'Tanggal Mulai Kontrak', 'required' => true, 'example' => '2024-01-05', 'desc' => 'Format YYYY-MM-DD. Wajib.'],
            ['key' => 'tanggal_selesai_kontrak', 'header' => 'Tanggal Selesai Kontrak', 'required' => false, 'example' => '2025-01-04', 'desc' => 'Format YYYY-MM-DD. Wajib untuk semua jenis KECUALI PKWTT (kosongkan bila PKWTT).'],
            ['key' => 'status_kontrak', 'header' => 'Status Kontrak', 'required' => false, 'example' => 'Aktif', 'desc' => 'Opsional (default Aktif). Umumnya "Aktif" untuk karyawan baru.'],
            ['key' => 'catatan_kontrak', 'header' => 'Catatan Kontrak', 'required' => false, 'example' => '', 'desc' => 'Opsional.'],
            ['key' => 'pin_mesin_absensi', 'header' => 'PIN Mesin Absensi', 'required' => false, 'example' => '1001', 'desc' => 'Opsional. PIN / ID user di mesin fingerprint. Jika diisi, Serial Number Mesin Absensi wajib diisi, dan PIN harus unik pada mesin tersebut. Boleh dikosongkan dulu dan diatur nanti lewat menu Edit karyawan.'],
            ['key' => 'serial_number_mesin_absensi', 'header' => 'Serial Number Mesin Absensi', 'required' => false, 'example' => 'CJXX204660001', 'desc' => 'Serial number mesin fingerprint tempat PIN terdaftar. Wajib jika PIN Mesin Absensi diisi; harus cocok dengan mesin yang sudah terdaftar di sistem.'],
            ['key' => 'password_login', 'header' => 'Password Login', 'required' => false, 'example' => 'rahasia123', 'desc' => 'Opsional. Jika diisi (bersama Email), akun login dibuat dengan password ini. Minimal 8 karakter.'],
            ['key' => 'peran_login', 'header' => 'Peran Login', 'required' => false, 'example' => 'Karyawan', 'desc' => 'Opsional. Nama peran/role untuk akun login. Jika kosong, memakai peran default jabatan. Hanya berlaku bila Password Login diisi.'],
        ];
    }

    /**
     * Flat, human-readable errors for the modal — each prefixed with its row.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return array_map(
            fn (array $e) => $e['row'] !== null ? "Baris {$e['row']}: {$e['message']}" : $e['message'],
            $this->rowErrors,
        );
    }

    /**
     * The same problems, structured, for building the downloadable error report.
     *
     * @return list<array{row: ?int, column: ?string, message: string}>
     */
    public function rowErrors(): array
    {
        return $this->rowErrors;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    private function addError(?int $row, string $message, ?string $column = null): void
    {
        $this->rowErrors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->addError(null, 'File tidak berisi data karyawan. Pastikan data diisi mulai baris ke-2.');

            return;
        }

        $lookups = $this->buildLookups();

        // A manager may be referenced by code (only existing employees have one) or
        // by full name — including the name of somebody created by another row here.
        $fileNames = $rows
            ->map(fn ($row) => strtolower(trim((string) $this->normalize($row)->get('nama_lengkap'))))
            ->filter()
            ->all();
        $knownManagerRefs = array_merge($lookups['employee_numbers'], $lookups['names'], $fileNames);

        $seen = ['nomor_kontrak' => [], 'email' => [], 'pin' => []];

        /** @var list<array<string, mixed>> $prepared */
        $prepared = [];

        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2; // heading is row 1, data starts at row 2
            $row = $this->normalize($rawRow);
            $data = $this->validateRow($row, $rowNumber, $lookups, $knownManagerRefs, $seen);

            if ($data !== null) {
                $prepared[] = $data;
            }
        }

        if ($this->rowErrors !== []) {
            return; // all-or-nothing: do not persist a partially valid file
        }

        $this->persist($prepared);
    }

    /**
     * @param  array<string, list<string>|array<string, int>>  $lookups
     * @param  list<string>  $knownManagerRefs
     * @param  array<string, array<string, bool>>  $seen
     * @return array<string, mixed>|null
     */
    private function validateRow(Collection $row, int $rowNumber, array $lookups, array $knownManagerRefs, array &$seen): ?array
    {
        $before = count($this->rowErrors);
        $add = fn (string $message, ?string $column = null) => $this->addError($rowNumber, $message, $column);

        $get = fn (string $key) => trim((string) ($row->get($key) ?? ''));

        $fullName = $get('nama_lengkap');
        $email = $get('email');
        $branchName = $get('lokasi_kerja');
        $departmentName = $get('divisi');
        $positionName = $get('jabatan');
        $managerRef = $get('nomor_nama_atasan');
        $contractNumber = $get('nomor_kontrak');
        $contractType = $get('jenis_kontrak');
        $pin = $get('pin_mesin_absensi');
        $serial = $get('serial_number_mesin_absensi');
        $loginPassword = $get('password_login');
        $loginRole = $get('peran_login');

        // Required, non-reference fields. Lokasi Kerja / Divisi / Jabatan are
        // required as text but auto-created on persist when they don't exist yet.
        foreach (['nama_lengkap' => 'Nama Lengkap', 'tanggal_bergabung' => 'Tanggal Bergabung', 'lokasi_kerja' => 'Lokasi Kerja', 'divisi' => 'Divisi', 'jabatan' => 'Jabatan', 'nomor_kontrak' => 'Nomor Kontrak', 'jenis_kontrak' => 'Jenis Kontrak', 'tanggal_mulai_kontrak' => 'Tanggal Mulai Kontrak'] as $key => $label) {
            if ($get($key) === '') {
                $add("kolom \"{$label}\" wajib diisi.", $label);
            }
        }

        // The importer's own data scope: a row may not place someone in a location or
        // division the importer is not allowed to see.
        if ($this->allowedBranches !== null && $branchName !== '' && ! in_array(strtolower($branchName), $this->allowedBranches, true)) {
            $add("Lokasi Kerja \"{$branchName}\" berada di luar cakupan akses Anda.", 'Lokasi Kerja');
        }

        if ($this->allowedDepartments !== null && $departmentName !== '' && ! in_array(strtolower($departmentName), $this->allowedDepartments, true)) {
            $add("Divisi \"{$departmentName}\" berada di luar cakupan akses Anda.", 'Divisi');
        }

        // Uniqueness within the file and against existing data (case-insensitive;
        // the lookup lists are already lowercased). Nomor Karyawan is not checked:
        // it is generated on save, so whatever the file says is ignored.
        $this->checkUnique(strtolower($contractNumber), 'nomor_kontrak', $lookups['contract_numbers'], 'Nomor Kontrak', $seen, $add, $contractNumber);

        if ($email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $add('format Email tidak valid.', 'Email');
            } else {
                $this->checkUnique(strtolower($email), 'email', $lookups['emails'], 'Email', $seen, $add, $email);
            }
        }

        // Attendance-machine PIN. Optional, but if given it must name a registered
        // machine (by serial number) and be unique on that machine.
        $deviceId = $this->resolve($serial, $lookups['devices']);

        if ($serial !== '' && $deviceId === null) {
            $add("Serial Number Mesin Absensi \"{$serial}\" tidak ditemukan. Daftarkan mesin terlebih dahulu.", 'Serial Number Mesin Absensi');
        }

        if ($pin !== '' && $serial === '') {
            $add('Serial Number Mesin Absensi wajib diisi jika PIN Mesin Absensi diisi.', 'Serial Number Mesin Absensi');
        }

        if ($pin === '' && $serial !== '') {
            $add('PIN Mesin Absensi wajib diisi jika Serial Number Mesin Absensi diisi.', 'PIN Mesin Absensi');
        }

        if ($pin !== '' && $deviceId !== null) {
            // PINs are unique per machine: the same PIN on a different machine is fine.
            $this->checkUnique($deviceId.'|'.strtolower($pin), 'pin', $lookups['pins'], 'PIN Mesin Absensi', $seen, $add, $pin);
        }

        if ($managerRef !== '' && ! in_array(strtolower($managerRef), $knownManagerRefs, true)) {
            $add("Atasan \"{$managerRef}\" tidak ditemukan, baik sebagai kode karyawan maupun sebagai nama karyawan.", 'Nomor / Nama Atasan');
        }

        // Login account. Created only when both Email and Password Login are given.
        $roleId = $this->resolve($loginRole, $lookups['roles']);

        if ($loginPassword !== '' && $email === '') {
            $add('Email wajib diisi jika Password Login diisi (akun login butuh email).', 'Password Login');
        }

        if ($loginPassword !== '' && mb_strlen($loginPassword) < 8) {
            $add('Password Login minimal 8 karakter.', 'Password Login');
        }

        if ($loginRole !== '' && $roleId === null) {
            $add("Peran Login \"{$loginRole}\" tidak ditemukan.", 'Peran Login');
        }

        if ($loginRole !== '' && $loginPassword === '') {
            $add('Peran Login hanya berlaku jika Password Login diisi.', 'Peran Login');
        }

        $employmentStatus = $this->normalizeEmploymentStatus($get('status_kepegawaian'));
        if ($employmentStatus === null) {
            $add('Status Kepegawaian harus salah satu dari: Aktif, Nonaktif.', 'Status Kepegawaian');
        }

        if ($contractType !== '' && ! in_array($contractType, ['PKWT', 'PKWTT', 'Probation', 'Internship'], true)) {
            $add('Jenis Kontrak harus salah satu dari: PKWT, PKWTT, Probation, Internship.', 'Jenis Kontrak');
        }

        $contractStatus = $this->normalizeContractStatus($get('status_kontrak'));
        if ($contractStatus === null) {
            $add('Status Kontrak tidak dikenali. Kosongkan untuk default "Aktif".', 'Status Kontrak');
        }

        // Dates.
        $birthDate = $this->parseDate($get('tanggal_lahir'), 'Tanggal Lahir', $add, required: false);
        $joinDate = $this->parseDate($get('tanggal_bergabung'), 'Tanggal Bergabung', $add, required: true);
        $contractStart = $this->parseDate($get('tanggal_mulai_kontrak'), 'Tanggal Mulai Kontrak', $add, required: true);
        $contractEnd = $this->parseDate($get('tanggal_selesai_kontrak'), 'Tanggal Selesai Kontrak', $add, required: false);

        if ($birthDate && $birthDate->startOfDay() >= CarbonImmutable::now()->startOfDay()) {
            $add('Tanggal Lahir harus sebelum hari ini.', 'Tanggal Lahir');
        }

        if ($contractType !== 'PKWTT' && $contractType !== '' && $contractEnd === null && $get('tanggal_selesai_kontrak') === '') {
            $add('Tanggal Selesai Kontrak wajib diisi untuk jenis kontrak selain PKWTT.', 'Tanggal Selesai Kontrak');
        }

        if ($contractStart && $contractEnd && $contractEnd < $contractStart) {
            $add('Tanggal Selesai Kontrak tidak boleh sebelum Tanggal Mulai Kontrak.', 'Tanggal Selesai Kontrak');
        }

        if (count($this->rowErrors) > $before) {
            return null;
        }

        return [
            'placement' => [
                'branch' => $branchName,
                'department' => $departmentName,
                'position' => $positionName,
            ],
            'employee' => [
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'phone' => $get('telepon') !== '' ? $get('telepon') : null,
                'identity_number' => $get('nik') !== '' ? $get('nik') : null,
                'birth_date' => $birthDate,
                'join_date' => $joinDate,
                'employment_status' => $employmentStatus,
                'address' => $get('alamat') !== '' ? $get('alamat') : null,
            ],
            'contract' => [
                'contract_number' => $contractNumber,
                'contract_type' => $contractType,
                'start_date' => $contractStart,
                'end_date' => $contractType === 'PKWTT' ? null : $contractEnd,
                'status' => $contractStatus,
                'notes' => $get('catatan_kontrak') !== '' ? $get('catatan_kontrak') : null,
            ],
            'pin' => $pin !== '' ? $pin : null,
            'device_id' => $deviceId,
            'login' => $loginPassword !== '' && $email !== ''
                ? ['password' => $loginPassword, 'role_id' => $roleId]
                : null,
            'manager_ref' => $managerRef !== '' ? strtolower($managerRef) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $prepared
     */
    private function persist(array $prepared): void
    {
        $ingestion = app(PunchIngestionService::class);

        DB::transaction(function () use ($prepared, $ingestion) {
            // Managers are referenced by code or by name, so both maps are kept and
            // grown as rows are created (a manager may be created by this very file).
            /** @var array<string, int> $numberToId */
            $numberToId = Employee::query()->pluck('id', 'employee_number')
                ->mapWithKeys(fn ($id, $number) => [strtolower((string) $number) => $id])
                ->all();

            /** @var array<string, int> $nameToId */
            $nameToId = [];

            foreach (Employee::query()->orderBy('id')->get(['id', 'full_name']) as $existing) {
                // First one wins: on duplicate names the oldest employee is assumed.
                $nameToId[strtolower(trim($existing->full_name))] ??= $existing->id;
            }

            // Case-insensitive name => id caches so a placement referenced by many
            // rows is created only once, seeded with everything already on record.
            $branches = $this->nameIdCache(Branch::class);
            $departments = $this->nameIdCache(Department::class);
            $positions = $this->nameIdCache(JobPosition::class);

            $branchDepartment = DB::table('branch_department')->get(['branch_id', 'department_id'])
                ->mapWithKeys(fn ($row) => [$row->branch_id.'|'.$row->department_id => true])->all();
            $departmentPosition = DB::table('department_job_position')->get(['department_id', 'job_position_id'])
                ->mapWithKeys(fn ($row) => [$row->department_id.'|'.$row->job_position_id => true])->all();

            /** @var list<array{employee: Employee, manager_ref: ?string}> $withManagers */
            $withManagers = [];

            foreach ($prepared as $item) {
                $branchId = $this->resolveOrCreate($branches, Branch::class, $item['placement']['branch']);
                $departmentId = $this->resolveOrCreate($departments, Department::class, $item['placement']['department']);
                $positionId = $this->resolveOrCreate($positions, JobPosition::class, $item['placement']['position']);

                $this->ensureLink($branchDepartment, 'branch_department', ['branch_id' => $branchId, 'department_id' => $departmentId], ['is_primary' => false]);
                $this->ensureLink($departmentPosition, 'department_job_position', ['department_id' => $departmentId, 'job_position_id' => $positionId]);

                $employee = Employee::query()->create($item['employee'] + [
                    'branch_id' => $branchId,
                    'department_id' => $departmentId,
                    'job_position_id' => $positionId,
                ]);

                $contract = $employee->contracts()->create($item['contract']);

                if ($item['pin'] !== null) {
                    $device = $item['device_id'] ? Device::find($item['device_id']) : null;
                    $ingestion->assignPin($employee, $device, $item['pin']);
                }

                if ($item['login'] !== null) {
                    $this->createLoginAccount($employee, $item['login']);
                }

                $employee->recordEvent('joined', 'Bergabung sebagai karyawan (impor Excel).', $employee->join_date);
                $employee->recordEvent(
                    'contract_created',
                    "Kontrak {$contract->contract_number} ({$contract->contract_type}) dibuat (impor Excel).",
                    $contract->start_date,
                    ['contract_number' => $contract->contract_number],
                );

                $numberToId[strtolower($employee->employee_number)] = $employee->id;
                $nameToId[strtolower(trim($employee->full_name))] ??= $employee->id;
                $withManagers[] = ['employee' => $employee, 'manager_ref' => $item['manager_ref']];
                $this->imported++;
            }

            // Second pass: wire up managers now that every employee has an id.
            foreach ($withManagers as $entry) {
                $managerRef = $entry['manager_ref'];

                if ($managerRef === null) {
                    continue;
                }

                $managerId = $numberToId[$managerRef] ?? $nameToId[$managerRef] ?? null;

                if ($managerId && $managerId !== $entry['employee']->id) {
                    $entry['employee']->forceFill(['manager_id' => $managerId])->save();
                }
            }
        });
    }

    /**
     * Case-insensitive "name => id" map of every existing record of a master model.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return array<string, int>
     */
    private function nameIdCache(string $model): array
    {
        return $model::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim((string) $name)) => $id])
            ->all();
    }

    /**
     * Resolve a master record by name from the cache, creating (and caching) an
     * active one when it does not exist yet. A brand-new Lokasi Kerja also gets a
     * code, because the employee code is built from it.
     *
     * @param  array<string, int>  $cache
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function resolveOrCreate(array &$cache, string $model, string $name): int
    {
        $key = strtolower(trim($name));

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $attributes = ['name' => trim($name), 'is_active' => true];

        if ($model === Branch::class) {
            $attributes['code'] = $this->branchCodeFor(trim($name));
        }

        $id = $model::query()->create($attributes)->id;
        $cache[$key] = $id;

        return $id;
    }

    /**
     * A short, unique code for a location the file introduces: the initials of a
     * multi-word name ("Kantor Pusat" => KP), otherwise its first three letters
     * ("Gudang" => GUD), with a counter appended when that is already taken.
     */
    private function branchCodeFor(string $name): string
    {
        $words = preg_split('/\s+/', preg_replace('/[^A-Za-z0-9 ]/', ' ', $name) ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $base = count($words) > 1
            ? implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 4)))
            : mb_substr($words[0] ?? '', 0, 3);

        $base = strtoupper($base) ?: 'LOC';
        $code = $base;
        $suffix = 1;

        while (Branch::query()->where('code', $code)->exists()) {
            $code = $base.++$suffix;
        }

        return $code;
    }

    /**
     * Ensure an active pivot row exists for the given key, tracking it in $existing
     * so we never insert the same link twice within one import.
     *
     * @param  array<string, bool>  $existing
     * @param  array<string, int>  $key
     * @param  array<string, mixed>  $extra
     */
    private function ensureLink(array &$existing, string $table, array $key, array $extra = []): void
    {
        $signature = implode('|', $key);

        if (isset($existing[$signature])) {
            return;
        }

        DB::table($table)->insert($key + $extra + [
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $existing[$signature] = true;
    }

    /**
     * Create the login account for a just-imported employee and assign its role
     * (the named one when given, otherwise the job position's default).
     *
     * @param  array{password: string, role_id: ?int}  $login
     */
    private function createLoginAccount(Employee $employee, array $login): void
    {
        $user = User::query()->create([
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => $login['password'],
        ]);

        $employee->forceFill(['user_id' => $user->id])->save();

        $roleModel = config('permission.models.role');
        $role = $login['role_id'] !== null
            ? $roleModel::query()->find($login['role_id'])
            : $employee->loadMissing('jobPosition.defaultRole')->jobPosition?->defaultRole;

        if ($role) {
            $user->syncRoles([$role->name]);
        }
    }

    /**
     * Normalise a raw row so lookups by our snake_case keys work regardless of the
     * configured heading-row formatter (slug vs none).
     */
    private function normalize(mixed $row): Collection
    {
        return collect($row instanceof Collection ? $row->all() : (array) $row)
            ->mapWithKeys(fn ($value, $key) => [Str::slug((string) $key, '_') => $value]);
    }

    /**
     * @param  array<string, array<string, bool>>  $seen
     */
    private function checkUnique(string $value, string $bucket, array $existing, string $label, array &$seen, callable $add, ?string $display = null): void
    {
        if ($value === '') {
            return;
        }

        $shown = $display ?? $value;

        if (in_array($value, $existing, true)) {
            $add("{$label} \"{$shown}\" sudah dipakai karyawan lain.", $label);

            return;
        }

        if (isset($seen[$bucket][$value])) {
            $add("{$label} \"{$shown}\" muncul lebih dari sekali di file ini.", $label);

            return;
        }

        $seen[$bucket][$value] = true;
    }

    /**
     * @param  array<string, int>  $map  lowercased-name => id
     */
    private function resolve(string $name, array $map): ?int
    {
        if ($name === '') {
            return null;
        }

        return $map[strtolower($name)] ?? null;
    }

    private function normalizeEmploymentStatus(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'aktif', 'active' => 'active',
            'nonaktif', 'non-aktif', 'inactive', 'tidak aktif' => 'inactive',
            // Legacy values from older templates fold into "Aktif".
            'probation', 'skorsing', 'suspended', 'skorsing / ditangguhkan' => 'active',
            default => null,
        };
    }

    private function normalizeContractStatus(string $value): ?string
    {
        if (trim($value) === '') {
            return 'active';
        }

        $key = strtolower(trim($value));

        foreach (EmployeeContract::statusLabels() as $statusKey => $label) {
            if ($key === $statusKey || $key === strtolower($label)) {
                return $statusKey;
            }
        }

        return null;
    }

    private function parseDate(string $value, string $label, callable $add, bool $required): ?CarbonImmutable
    {
        if ($value === '') {
            if ($required) {
                $add("kolom \"{$label}\" wajib diisi (format YYYY-MM-DD).", $label);
            }

            return null;
        }

        if (is_numeric($value)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (\Throwable) {
                $add("{$label} tidak dapat dibaca sebagai tanggal.", $label);

                return null;
            }
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            $add("{$label} \"{$value}\" bukan tanggal yang valid (gunakan format YYYY-MM-DD).", $label);

            return null;
        }
    }

    /**
     * @return array{
     *     devices: array<string, int>,
     *     roles: array<string, int>,
     *     employee_numbers: list<string>,
     *     names: list<string>,
     *     contract_numbers: list<string>,
     *     emails: list<string>,
     *     pins: list<string>,
     * }
     */
    private function buildLookups(): array
    {
        // Registered machines keyed by serial number (Lokasi Kerja / Divisi /
        // Jabatan are no longer validated here — they are auto-created on persist).
        $devices = Device::query()->pluck('id', 'serial_number')
            ->mapWithKeys(fn ($id, $serial) => [strtolower(trim((string) $serial)) => $id])->all();

        $roles = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim((string) $name)) => $id])->all();

        return [
            'devices' => $devices,
            'roles' => $roles,
            'employee_numbers' => Employee::query()->pluck('employee_number')->map(fn ($v) => strtolower((string) $v))->all(),
            'names' => Employee::query()->pluck('full_name')->map(fn ($v) => strtolower(trim((string) $v)))->all(),
            'contract_numbers' =>EmployeeContract::query()->pluck('contract_number')->map(fn ($v) => strtolower((string) $v))->all(),
            'emails' => Employee::query()->whereNotNull('email')->pluck('email')->map(fn ($v) => strtolower((string) $v))
                ->merge(DB::table('users')->whereNotNull('email')->pluck('email')->map(fn ($v) => strtolower((string) $v)))
                ->unique()->values()->all(),
            // PINs are unique per machine, so they are keyed by "deviceId|pin".
            'pins' => DB::table('employee_devices')->get(['device_id', 'machine_user_id'])
                ->map(fn ($row) => ($row->device_id ?? '').'|'.strtolower((string) $row->machine_user_id))->all(),
        ];
    }
}
