<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobPosition;
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
 * Imports employees (with their placement, first contract and attendance-machine
 * PIN) from the accompanying Excel template. Validation is all-or-nothing: every
 * row is checked first and — only if the whole file is clean — the rows are
 * persisted. Otherwise no employee is created and the collected, human-readable
 * errors are surfaced to the user, one per problem, prefixed with the row number.
 */
class EmployeesImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /** @var list<string> */
    private array $errors = [];

    private int $imported = 0;

    /**
     * Column metadata, shared with the template/guide export so the header row,
     * the instructions sheet and this importer never drift apart.
     *
     * @return list<array{key: string, header: string, required: bool, example: string, desc: string}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'nomor_karyawan', 'header' => 'Nomor Karyawan', 'required' => true, 'example' => 'EMP-001', 'desc' => 'Nomor pegawai unik. Wajib, tidak boleh sama dengan karyawan lain atau baris lain.'],
            ['key' => 'nama_lengkap', 'header' => 'Nama Lengkap', 'required' => true, 'example' => 'Budi Santoso', 'desc' => 'Nama lengkap karyawan.'],
            ['key' => 'email', 'header' => 'Email', 'required' => false, 'example' => 'budi@contoh.com', 'desc' => 'Opsional. Jika diisi harus unik. Akun login TIDAK dibuat otomatis dari import.'],
            ['key' => 'telepon', 'header' => 'Telepon', 'required' => false, 'example' => '08123456789', 'desc' => 'Opsional.'],
            ['key' => 'nik', 'header' => 'NIK', 'required' => false, 'example' => '3201xxxxxxxxxxxx', 'desc' => 'Nomor identitas (KTP). Opsional.'],
            ['key' => 'tanggal_lahir', 'header' => 'Tanggal Lahir', 'required' => false, 'example' => '1995-04-17', 'desc' => 'Format YYYY-MM-DD. Opsional, harus sebelum hari ini.'],
            ['key' => 'tanggal_bergabung', 'header' => 'Tanggal Bergabung', 'required' => true, 'example' => '2024-01-05', 'desc' => 'Format YYYY-MM-DD. Wajib.'],
            ['key' => 'status_kepegawaian', 'header' => 'Status Kepegawaian', 'required' => true, 'example' => 'Aktif', 'desc' => 'Salah satu: Aktif, Probation, Skorsing.'],
            ['key' => 'alamat', 'header' => 'Alamat', 'required' => false, 'example' => 'Jl. Melati No. 1', 'desc' => 'Opsional.'],
            ['key' => 'lokasi_kerja', 'header' => 'Lokasi Kerja', 'required' => true, 'example' => 'Kantor Pusat', 'desc' => 'Nama lokasi/cabang; harus sudah terdaftar dan aktif.'],
            ['key' => 'divisi', 'header' => 'Divisi', 'required' => true, 'example' => 'Operasional', 'desc' => 'Nama divisi; harus tersedia pada lokasi kerja tersebut.'],
            ['key' => 'jabatan', 'header' => 'Jabatan', 'required' => true, 'example' => 'Staf', 'desc' => 'Nama jabatan; harus sesuai dengan divisi.'],
            ['key' => 'nomor_karyawan_atasan', 'header' => 'Nomor Karyawan Atasan', 'required' => false, 'example' => 'EMP-000', 'desc' => 'Opsional. Nomor karyawan atasan langsung (harus sudah ada, atau ada di file ini).'],
            ['key' => 'nomor_kontrak', 'header' => 'Nomor Kontrak', 'required' => true, 'example' => 'KTR-2024-001', 'desc' => 'Nomor kontrak unik. Wajib.'],
            ['key' => 'jenis_kontrak', 'header' => 'Jenis Kontrak', 'required' => true, 'example' => 'PKWT', 'desc' => 'Salah satu: PKWT, PKWTT, Probation, Internship.'],
            ['key' => 'tanggal_mulai_kontrak', 'header' => 'Tanggal Mulai Kontrak', 'required' => true, 'example' => '2024-01-05', 'desc' => 'Format YYYY-MM-DD. Wajib.'],
            ['key' => 'tanggal_selesai_kontrak', 'header' => 'Tanggal Selesai Kontrak', 'required' => false, 'example' => '2025-01-04', 'desc' => 'Format YYYY-MM-DD. Wajib untuk semua jenis KECUALI PKWTT (kosongkan bila PKWTT).'],
            ['key' => 'status_kontrak', 'header' => 'Status Kontrak', 'required' => false, 'example' => 'Aktif', 'desc' => 'Opsional (default Aktif). Umumnya "Aktif" untuk karyawan baru.'],
            ['key' => 'catatan_kontrak', 'header' => 'Catatan Kontrak', 'required' => false, 'example' => '', 'desc' => 'Opsional.'],
            ['key' => 'pin_mesin_absensi', 'header' => 'PIN Mesin Absensi', 'required' => true, 'example' => '1001', 'desc' => 'PIN / ID user di mesin fingerprint. Wajib dan unik antar karyawan.'],
        ];
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'File tidak berisi data karyawan. Pastikan data diisi mulai baris ke-2.';

            return;
        }

        $lookups = $this->buildLookups();

        // Employee numbers that will exist after the import (existing + in-file),
        // used to validate manager references that may point at a row in this file.
        $fileEmployeeNumbers = $rows
            ->map(fn ($row) => $this->normalize($row)->get('nomor_karyawan'))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->all();
        $knownEmployeeNumbers = array_merge($lookups['employee_numbers'], $fileEmployeeNumbers);

        $seen = ['nomor_karyawan' => [], 'nomor_kontrak' => [], 'email' => [], 'pin' => []];

        /** @var list<array<string, mixed>> $prepared */
        $prepared = [];

        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2; // heading is row 1, data starts at row 2
            $row = $this->normalize($rawRow);
            $data = $this->validateRow($row, $rowNumber, $lookups, $knownEmployeeNumbers, $seen);

            if ($data !== null) {
                $prepared[] = $data;
            }
        }

        if ($this->errors !== []) {
            return; // all-or-nothing: do not persist a partially valid file
        }

        $this->persist($prepared);
    }

    /**
     * @param  array<string, list<string>|array<string, int>>  $lookups
     * @param  list<string>  $knownEmployeeNumbers
     * @param  array<string, array<string, bool>>  $seen
     * @return array<string, mixed>|null
     */
    private function validateRow(Collection $row, int $rowNumber, array $lookups, array $knownEmployeeNumbers, array &$seen): ?array
    {
        $before = count($this->errors);
        $add = fn (string $message) => $this->errors[] = "Baris {$rowNumber}: {$message}";

        $get = fn (string $key) => trim((string) ($row->get($key) ?? ''));

        $employeeNumber = $get('nomor_karyawan');
        $fullName = $get('nama_lengkap');
        $email = $get('email');
        $branchName = $get('lokasi_kerja');
        $departmentName = $get('divisi');
        $positionName = $get('jabatan');
        $managerNumber = $get('nomor_karyawan_atasan');
        $contractNumber = $get('nomor_kontrak');
        $contractType = $get('jenis_kontrak');
        $pin = $get('pin_mesin_absensi');

        // Required, non-reference fields.
        foreach (['nomor_karyawan' => 'Nomor Karyawan', 'nama_lengkap' => 'Nama Lengkap', 'tanggal_bergabung' => 'Tanggal Bergabung', 'lokasi_kerja' => 'Lokasi Kerja', 'divisi' => 'Divisi', 'jabatan' => 'Jabatan', 'nomor_kontrak' => 'Nomor Kontrak', 'jenis_kontrak' => 'Jenis Kontrak', 'tanggal_mulai_kontrak' => 'Tanggal Mulai Kontrak', 'pin_mesin_absensi' => 'PIN Mesin Absensi'] as $key => $label) {
            if ($get($key) === '') {
                $add("kolom \"{$label}\" wajib diisi.");
            }
        }

        // Uniqueness within the file and against existing data (case-insensitive;
        // the lookup lists are already lowercased).
        $this->checkUnique(strtolower($employeeNumber), 'nomor_karyawan', $lookups['employee_numbers'], 'Nomor Karyawan', $seen, $add, $employeeNumber);
        $this->checkUnique(strtolower($contractNumber), 'nomor_kontrak', $lookups['contract_numbers'], 'Nomor Kontrak', $seen, $add, $contractNumber);
        $this->checkUnique(strtolower($pin), 'pin', $lookups['pins'], 'PIN Mesin Absensi', $seen, $add, $pin);

        if ($email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $add('format Email tidak valid.');
            } else {
                $this->checkUnique(strtolower($email), 'email', $lookups['emails'], 'Email', $seen, $add, $email);
            }
        }

        // Placement references (resolved by name).
        $branchId = $this->resolve($branchName, $lookups['branches']);
        if ($branchName !== '' && $branchId === null) {
            $add("Lokasi Kerja \"{$branchName}\" tidak ditemukan atau tidak aktif.");
        }

        $departmentId = $this->resolve($departmentName, $lookups['departments']);
        if ($departmentName !== '' && $departmentId === null) {
            $add("Divisi \"{$departmentName}\" tidak ditemukan atau tidak aktif.");
        }

        $positionId = $this->resolve($positionName, $lookups['positions']);
        if ($positionName !== '' && $positionId === null) {
            $add("Jabatan \"{$positionName}\" tidak ditemukan atau tidak aktif.");
        }

        if ($branchId && $departmentId && ! isset($lookups['branch_department'][$branchId.'|'.$departmentId])) {
            $add("Divisi \"{$departmentName}\" tidak tersedia pada Lokasi Kerja \"{$branchName}\".");
        }

        if ($departmentId && $positionId && ! isset($lookups['department_position'][$departmentId.'|'.$positionId])) {
            $add("Jabatan \"{$positionName}\" tidak sesuai dengan Divisi \"{$departmentName}\".");
        }

        if ($managerNumber !== '' && ! in_array(strtolower($managerNumber), $knownEmployeeNumbers, true)) {
            $add("Nomor Karyawan Atasan \"{$managerNumber}\" tidak ditemukan.");
        }

        $employmentStatus = $this->normalizeEmploymentStatus($get('status_kepegawaian'));
        if ($employmentStatus === null) {
            $add('Status Kepegawaian harus salah satu dari: Aktif, Probation, Skorsing.');
        }

        if ($contractType !== '' && ! in_array($contractType, ['PKWT', 'PKWTT', 'Probation', 'Internship'], true)) {
            $add('Jenis Kontrak harus salah satu dari: PKWT, PKWTT, Probation, Internship.');
        }

        $contractStatus = $this->normalizeContractStatus($get('status_kontrak'));
        if ($contractStatus === null) {
            $add('Status Kontrak tidak dikenali. Kosongkan untuk default "Aktif".');
        }

        // Dates.
        $birthDate = $this->parseDate($get('tanggal_lahir'), 'Tanggal Lahir', $add, required: false);
        $joinDate = $this->parseDate($get('tanggal_bergabung'), 'Tanggal Bergabung', $add, required: true);
        $contractStart = $this->parseDate($get('tanggal_mulai_kontrak'), 'Tanggal Mulai Kontrak', $add, required: true);
        $contractEnd = $this->parseDate($get('tanggal_selesai_kontrak'), 'Tanggal Selesai Kontrak', $add, required: false);

        if ($birthDate && $birthDate->startOfDay() >= CarbonImmutable::now()->startOfDay()) {
            $add('Tanggal Lahir harus sebelum hari ini.');
        }

        if ($contractType !== 'PKWTT' && $contractType !== '' && $contractEnd === null && $get('tanggal_selesai_kontrak') === '') {
            $add('Tanggal Selesai Kontrak wajib diisi untuk jenis kontrak selain PKWTT.');
        }

        if ($contractStart && $contractEnd && $contractEnd < $contractStart) {
            $add('Tanggal Selesai Kontrak tidak boleh sebelum Tanggal Mulai Kontrak.');
        }

        if (count($this->errors) > $before) {
            return null;
        }

        return [
            'employee' => [
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'job_position_id' => $positionId,
                'employee_number' => $employeeNumber,
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
            'pin' => $pin,
            'manager_number' => $managerNumber !== '' ? strtolower($managerNumber) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $prepared
     */
    private function persist(array $prepared): void
    {
        $ingestion = app(PunchIngestionService::class);

        DB::transaction(function () use ($prepared, $ingestion) {
            /** @var array<string, int> $numberToId */
            $numberToId = Employee::query()->pluck('id', 'employee_number')
                ->mapWithKeys(fn ($id, $number) => [strtolower((string) $number) => $id])
                ->all();

            /** @var list<array{employee: Employee, manager_number: ?string}> $withManagers */
            $withManagers = [];

            foreach ($prepared as $item) {
                $employee = Employee::query()->create($item['employee']);

                $contract = $employee->contracts()->create($item['contract']);
                $ingestion->assignPin($employee, null, $item['pin']);

                $employee->recordEvent('joined', 'Bergabung sebagai karyawan (impor Excel).', $employee->join_date);
                $employee->recordEvent(
                    'contract_created',
                    "Kontrak {$contract->contract_number} ({$contract->contract_type}) dibuat (impor Excel).",
                    $contract->start_date,
                    ['contract_number' => $contract->contract_number],
                );

                $numberToId[strtolower($employee->employee_number)] = $employee->id;
                $withManagers[] = ['employee' => $employee, 'manager_number' => $item['manager_number']];
                $this->imported++;
            }

            // Second pass: wire up managers now that every employee has an id.
            foreach ($withManagers as $entry) {
                $managerNumber = $entry['manager_number'];

                if ($managerNumber === null) {
                    continue;
                }

                $managerId = $numberToId[$managerNumber] ?? null;

                if ($managerId && $managerId !== $entry['employee']->id) {
                    $entry['employee']->forceFill(['manager_id' => $managerId])->save();
                }
            }
        });
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
            $add("{$label} \"{$shown}\" sudah dipakai karyawan lain.");

            return;
        }

        if (isset($seen[$bucket][$value])) {
            $add("{$label} \"{$shown}\" muncul lebih dari sekali di file ini.");

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
            'probation' => 'probation',
            'skorsing', 'suspended', 'skorsing / ditangguhkan' => 'suspended',
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
                $add("kolom \"{$label}\" wajib diisi (format YYYY-MM-DD).");
            }

            return null;
        }

        if (is_numeric($value)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (\Throwable) {
                $add("{$label} tidak dapat dibaca sebagai tanggal.");

                return null;
            }
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            $add("{$label} \"{$value}\" bukan tanggal yang valid (gunakan format YYYY-MM-DD).");

            return null;
        }
    }

    /**
     * @return array{
     *     branches: array<string, int>,
     *     departments: array<string, int>,
     *     positions: array<string, int>,
     *     branch_department: array<string, bool>,
     *     department_position: array<string, bool>,
     *     employee_numbers: list<string>,
     *     contract_numbers: list<string>,
     *     emails: list<string>,
     *     pins: list<string>,
     * }
     */
    private function buildLookups(): array
    {
        $branches = Branch::query()->where('is_active', true)->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => $id])->all();

        $departments = Department::query()->where('is_active', true)->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => $id])->all();

        $positions = JobPosition::query()->where('is_active', true)->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => $id])->all();

        $branchDepartment = DB::table('branch_department')->where('is_active', true)
            ->get(['branch_id', 'department_id'])
            ->mapWithKeys(fn ($row) => [$row->branch_id.'|'.$row->department_id => true])->all();

        $departmentPosition = DB::table('department_job_position')->where('is_active', true)
            ->get(['department_id', 'job_position_id'])
            ->mapWithKeys(fn ($row) => [$row->department_id.'|'.$row->job_position_id => true])->all();

        return [
            'branches' => $branches,
            'departments' => $departments,
            'positions' => $positions,
            'branch_department' => $branchDepartment,
            'department_position' => $departmentPosition,
            'employee_numbers' => Employee::query()->pluck('employee_number')->map(fn ($v) => strtolower((string) $v))->all(),
            'contract_numbers' => EmployeeContract::query()->pluck('contract_number')->map(fn ($v) => strtolower((string) $v))->all(),
            'emails' => Employee::query()->whereNotNull('email')->pluck('email')->map(fn ($v) => strtolower((string) $v))
                ->merge(DB::table('users')->whereNotNull('email')->pluck('email')->map(fn ($v) => strtolower((string) $v)))
                ->unique()->values()->all(),
            'pins' => DB::table('employee_devices')->whereNull('device_id')->pluck('machine_user_id')->map(fn ($v) => strtolower((string) $v))->all(),
        ];
    }
}
