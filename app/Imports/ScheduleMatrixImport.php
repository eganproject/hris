<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Services\ScheduleGenerator;
use App\Support\DataScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports a monthly roster from the matrix template: one row per employee, one
 * column per day of the month, each cell holding a shift code or a day-off token.
 * This is the shape HR already keeps its rosters in, so a month of schedule for
 * the whole workforce is a single sheet rather than thousands of rows.
 *
 * Sheet layout (see ScheduleTemplateExport, which builds it):
 *
 *   A1 "Periode"  B1 "2026-08"          <- row 1: the month the grid belongs to
 *   A2 "Nomor Karyawan"  B2 "Nama Lengkap"  C2.. 1,2,3,…,31   <- row 2: headers
 *   A3.. data                                                  <- row 3 onwards
 *
 * The period lives inside the file rather than being picked at upload time: a
 * roster written into the wrong month would silently overwrite a good one with
 * manual overrides the generator will never repair.
 *
 * Validation is all-or-nothing, matching EmployeesImport: the whole file is
 * checked first and nothing is written unless every cell is clean.
 *
 * Written days become manual overrides (ScheduleSource::Manual) so the nightly
 * rolling generator never clobbers a hand-made roster. Days already in the past
 * that carry an attendance row are re-resolved afterwards, so a schedule filled
 * in late turns a wrongly-recorded "Libur" into the correct "Alfa"/"Hadir".
 */
class ScheduleMatrixImport implements ToCollection, WithMultipleSheets
{
    /** 1-indexed spreadsheet row holding "Periode" + the month value. */
    public const PERIOD_ROW = 1;

    /** 1-indexed spreadsheet row holding the column headers. */
    public const HEADER_ROW = 2;

    /** 1-indexed spreadsheet row the employee data starts on. */
    public const FIRST_DATA_ROW = 3;

    public const COLUMN_EMPLOYEE_NUMBER = 'Nomor Karyawan';

    public const COLUMN_EMPLOYEE_NAME = 'Nama Lengkap';

    /** Cell values that mean "this employee is off that day". */
    public const DAY_OFF_TOKENS = ['L', 'LIBUR', 'OFF', 'X', '-'];

    /** Appended to a shift code to mark the day as work-from-home, e.g. "P/WFH". */
    public const WFH_SUFFIX = 'WFH';

    /**
     * @var list<array{row: ?int, column: ?string, message: string}>
     */
    private array $rowErrors = [];

    private ?CarbonImmutable $period = null;

    private int $importedEmployees = 0;

    private int $importedDays = 0;

    private int $reprocessedAttendances = 0;

    /**
     * @param  User|null  $actor  the importer, whose data scope every row must fall
     *                            inside; null skips the scope check (console use)
     */
    public function __construct(private readonly ?User $actor = null) {}

    /**
     * Only the first sheet carries data. Without this, maatwebsite would run the
     * importer over the "Petunjuk" sheet as well and report it as malformed.
     *
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /**
     * Flat, human-readable errors for the import modal — each prefixed with its row.
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

    public function importedEmployees(): int
    {
        return $this->importedEmployees;
    }

    public function importedDays(): int
    {
        return $this->importedDays;
    }

    public function reprocessedAttendances(): int
    {
        return $this->reprocessedAttendances;
    }

    public function period(): ?CarbonImmutable
    {
        return $this->period;
    }

    public function collection(Collection $rows): void
    {
        // Re-key to 1-indexed spreadsheet row numbers so every message we produce
        // points at the row the user actually sees in Excel.
        $rows = $rows->values();

        $period = $this->readPeriod($rows->get(self::PERIOD_ROW - 1));

        if ($period === null) {
            return;
        }

        $this->period = $period;

        $dayColumns = $this->readDayColumns($rows->get(self::HEADER_ROW - 1), $period);

        if ($dayColumns === []) {
            return;
        }

        $employees = $this->scopedEmployees();

        if ($employees->isEmpty()) {
            $this->addError(null, 'Tidak ada karyawan aktif dalam cakupan akses Anda, jadi tidak ada jadwal yang bisa diimpor.');

            return;
        }

        $byNumber = $employees->keyBy(fn (Employee $e) => mb_strtolower(trim((string) $e->employee_number)));
        $byName = $employees->groupBy(fn (Employee $e) => mb_strtolower(trim((string) $e->full_name)));

        $shifts = Shift::query()->where('is_active', true)->get()
            ->keyBy(fn (Shift $shift) => mb_strtolower(trim((string) $shift->code)));

        /** @var array<int, true> $seen employee id => already used by an earlier row */
        $seen = [];

        /** @var list<array{employee: Employee, days: list<array{date: CarbonImmutable, shift_id: ?int, is_day_off: bool, is_wfh: bool}>}> $prepared */
        $prepared = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if ($rowNumber < self::FIRST_DATA_ROW) {
                continue;
            }

            $row = collect($row instanceof Collection ? $row->all() : (array) $row)->values();

            if ($this->isBlank($row)) {
                continue; // spacer rows in a hand-edited sheet are not an error
            }

            $employee = $this->resolveEmployee($row, $rowNumber, $byNumber, $byName, $seen);
            $days = $this->readDays($row, $rowNumber, $dayColumns, $shifts, $period);

            if ($employee !== null && $days !== []) {
                $prepared[] = ['employee' => $employee, 'days' => $days];
            }
        }

        if ($this->rowErrors !== []) {
            return; // all-or-nothing: never persist a partially valid roster
        }

        if ($prepared === []) {
            $this->addError(null, 'Tidak ada satu pun sel jadwal yang terisi. Isi kode shift atau "L" pada kolom tanggal, lalu unggah ulang.');

            return;
        }

        $this->persist($prepared);
    }

    /**
     * Read the month the grid belongs to from row 1. Accepts "2026-08", a real
     * Excel date, or anything Carbon can parse into a month.
     */
    private function readPeriod(mixed $row): ?CarbonImmutable
    {
        $cells = collect($row instanceof Collection ? $row->all() : (array) $row)->values();

        // The label sits in A1; the value is the first non-empty cell after it.
        $raw = $cells->skip(1)->first(fn ($value) => trim((string) $value) !== '');
        $raw = trim((string) $raw);

        if ($raw === '') {
            $this->addError(null, 'Periode tidak ditemukan. Sel B1 harus berisi bulan jadwal, contoh "2026-08". Gunakan template terbaru agar formatnya benar.');

            return null;
        }

        if (is_numeric($raw)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfMonth();
            } catch (\Throwable) {
                $this->addError(null, "Periode \"{$raw}\" tidak dapat dibaca sebagai bulan. Gunakan format YYYY-MM, contoh \"2026-08\".");

                return null;
            }
        }

        try {
            return CarbonImmutable::parse($raw)->startOfMonth();
        } catch (\Throwable) {
            $this->addError(null, "Periode \"{$raw}\" bukan bulan yang valid. Gunakan format YYYY-MM, contoh \"2026-08\".");

            return null;
        }
    }

    /**
     * Map each day-of-month column to its zero-based position in the row, from the
     * header in row 2. Columns beyond the month's length are rejected outright so a
     * "31" left over from a longer month cannot silently swallow data.
     *
     * @return array<int, int> day of month => column index
     */
    private function readDayColumns(mixed $row, CarbonImmutable $period): array
    {
        $cells = collect($row instanceof Collection ? $row->all() : (array) $row)->values();
        $daysInMonth = $period->daysInMonth;
        $columns = [];

        foreach ($cells as $index => $value) {
            $value = trim((string) $value);

            if ($value === '' || ! ctype_digit($value)) {
                continue; // "Nomor Karyawan" / "Nama Lengkap" and any trailing notes
            }

            $day = (int) $value;

            if ($day < 1 || $day > $daysInMonth) {
                $this->addError(
                    self::HEADER_ROW,
                    "Kolom tanggal \"{$day}\" tidak ada pada {$period->translatedFormat('F Y')} (bulan ini hanya sampai tanggal {$daysInMonth}). Hapus kolom tersebut atau unduh template untuk bulan yang benar.",
                    $value,
                );

                continue;
            }

            if (isset($columns[$day])) {
                $this->addError(self::HEADER_ROW, "Kolom tanggal \"{$day}\" muncul lebih dari sekali.", $value);

                continue;
            }

            $columns[$day] = $index;
        }

        if ($columns === [] && $this->rowErrors === []) {
            $this->addError(null, 'Tidak ada kolom tanggal yang terbaca pada baris judul (baris 2). Pastikan baris 2 berisi "Nomor Karyawan", "Nama Lengkap", lalu angka 1 sampai '.$daysInMonth.'.');
        }

        return $columns;
    }

    /**
     * @param  Collection<int, Employee>  $byNumber
     * @param  Collection<string, Collection<int, Employee>>  $byName
     * @param  array<int, true>  $seen
     */
    private function resolveEmployee(Collection $row, int $rowNumber, Collection $byNumber, Collection $byName, array &$seen): ?Employee
    {
        $number = trim((string) $row->get(0));
        $name = trim((string) $row->get(1));

        $employee = null;

        if ($number !== '') {
            $employee = $byNumber->get(mb_strtolower($number));

            if ($employee === null) {
                $this->addError($rowNumber, "Nomor Karyawan \"{$number}\" tidak ditemukan di antara karyawan aktif dalam cakupan akses Anda.", self::COLUMN_EMPLOYEE_NUMBER);

                return null;
            }
        } elseif ($name !== '') {
            // Falling back to the name keeps a hand-typed row usable, but only when
            // the name is unambiguous.
            $matches = $byName->get(mb_strtolower($name));

            if ($matches === null) {
                $this->addError($rowNumber, "Karyawan \"{$name}\" tidak ditemukan di antara karyawan aktif dalam cakupan akses Anda.", self::COLUMN_EMPLOYEE_NAME);

                return null;
            }

            if ($matches->count() > 1) {
                $this->addError($rowNumber, "Nama \"{$name}\" dipakai oleh lebih dari satu karyawan. Isi kolom Nomor Karyawan untuk memastikan orangnya.", self::COLUMN_EMPLOYEE_NUMBER);

                return null;
            }

            $employee = $matches->first();
        } else {
            $this->addError($rowNumber, 'Nomor Karyawan wajib diisi (atau isi Nama Lengkap bila nomornya belum diketahui).', self::COLUMN_EMPLOYEE_NUMBER);

            return null;
        }

        if (isset($seen[$employee->id])) {
            $this->addError($rowNumber, "Karyawan \"{$employee->full_name}\" muncul lebih dari sekali di file ini.", self::COLUMN_EMPLOYEE_NUMBER);

            return null;
        }

        $seen[$employee->id] = true;

        return $employee;
    }

    /**
     * Translate one employee's filled cells into schedule instructions. Empty cells
     * are skipped so a partially filled sheet only touches the days it names.
     *
     * @param  array<int, int>  $dayColumns
     * @param  Collection<string, Shift>  $shifts
     * @return list<array{date: CarbonImmutable, shift_id: ?int, is_day_off: bool, is_wfh: bool}>
     */
    private function readDays(Collection $row, int $rowNumber, array $dayColumns, Collection $shifts, CarbonImmutable $period): array
    {
        $days = [];

        foreach ($dayColumns as $day => $columnIndex) {
            $raw = trim((string) $row->get($columnIndex, ''));

            if ($raw === '') {
                continue; // "jangan ubah hari ini"
            }

            $isWfh = false;
            $code = $raw;

            // "P/WFH", "P-WFH", "P WFH" — the shift still applies, flagged as WFH.
            if (preg_match('/^(.*?)[\s\/\-_]+'.self::WFH_SUFFIX.'$/i', $raw, $matches) === 1) {
                $isWfh = true;
                $code = trim($matches[1]);
            }

            if ($code === '') {
                $this->addError($rowNumber, "Tanggal {$day}: \"{$raw}\" tidak menyebutkan kode shift. Tulis kode shift diikuti /WFH, contoh \"P/WFH\".", (string) $day);

                continue;
            }

            if (in_array(mb_strtoupper($code), self::DAY_OFF_TOKENS, true)) {
                if ($isWfh) {
                    $this->addError($rowNumber, "Tanggal {$day}: hari libur tidak bisa ditandai WFH.", (string) $day);

                    continue;
                }

                $days[] = ['date' => $period->setDay($day), 'shift_id' => null, 'is_day_off' => true, 'is_wfh' => false];

                continue;
            }

            $shift = $shifts->get(mb_strtolower($code));

            if ($shift === null) {
                $available = $shifts->keys()->map(fn ($c) => mb_strtoupper((string) $c))->implode(', ');

                $this->addError(
                    $rowNumber,
                    "Tanggal {$day}: kode shift \"{$code}\" tidak dikenali. Gunakan salah satu kode shift aktif (".($available ?: 'belum ada shift aktif').') atau "L" untuk libur.',
                    (string) $day,
                );

                continue;
            }

            $days[] = ['date' => $period->setDay($day), 'shift_id' => $shift->id, 'is_day_off' => false, 'is_wfh' => $isWfh];
        }

        return $days;
    }

    /**
     * @param  list<array{employee: Employee, days: list<array{date: CarbonImmutable, shift_id: ?int, is_day_off: bool, is_wfh: bool}>}>  $prepared
     */
    private function persist(array $prepared): void
    {
        $generator = app(ScheduleGenerator::class);
        $resolver = app(AttendanceResolver::class);
        $today = CarbonImmutable::today();

        DB::transaction(function () use ($prepared, $generator, $resolver, $today) {
            /** @var list<array{employee: Employee, date: CarbonImmutable}> $toReprocess */
            $toReprocess = [];

            foreach ($prepared as $entry) {
                foreach ($entry['days'] as $day) {
                    $generator->override(
                        $entry['employee'],
                        $day['date'],
                        $day['shift_id'],
                        $day['is_day_off'],
                        null,
                        $day['is_wfh'],
                    );

                    $this->importedDays++;

                    if (! $day['date']->greaterThan($today)) {
                        $toReprocess[] = ['employee' => $entry['employee'], 'date' => $day['date']];
                    }
                }

                $this->importedEmployees++;
            }

            // A roster filled in after the fact leaves stale attendance behind: days
            // resolved as "Libur" only because no shift was known. Re-resolve those
            // (punches and notes are preserved) so reports agree with the new roster.
            foreach ($toReprocess as $item) {
                $exists = Attendance::query()
                    ->where('employee_id', $item['employee']->id)
                    ->whereDate('work_date', $item['date']->toDateString())
                    ->exists();

                if (! $exists) {
                    continue; // the day was never closed out; the nightly job will do it
                }

                $resolver->reprocess($item['employee'], $item['date']);
                $this->reprocessedAttendances++;
            }
        });
    }

    /**
     * Active employees the importer is allowed to touch.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    private function scopedEmployees()
    {
        $query = $this->actor !== null
            ? DataScope::forAttendance($this->actor)->employees()
            : Employee::query();

        return $query->active()->get(['id', 'employee_number', 'full_name']);
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function isBlank(Collection $row): bool
    {
        return $row->every(fn ($value) => trim((string) $value) === '');
    }

    private function addError(?int $row, string $message, ?string $column = null): void
    {
        $this->rowErrors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }
}
