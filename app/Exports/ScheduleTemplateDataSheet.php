<?php

namespace App\Exports;

use App\Imports\ScheduleMatrixImport;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The fillable roster grid. Row 1 carries the period, row 2 the headers, row 3
 * onwards one employee each — the exact layout ScheduleMatrixImport parses.
 */
class ScheduleTemplateDataSheet implements FromArray, WithEvents, WithTitle
{
    private const WEEKEND_TINT = 'FDE8E8';

    /**
     * @param  EloquentCollection<int, Employee>  $employees
     * @param  EloquentCollection<int, Shift>  $shifts
     */
    public function __construct(
        private readonly CarbonImmutable $month,
        private readonly EloquentCollection $employees,
        private readonly EloquentCollection $shifts,
    ) {}

    public function title(): string
    {
        return 'Jadwal';
    }

    /** @return list<list<string|int>> */
    public function array(): array
    {
        $days = range(1, $this->month->daysInMonth);

        $rows = [
            ['Periode', $this->month->format('Y-m')],
            [ScheduleMatrixImport::COLUMN_EMPLOYEE_NUMBER, ScheduleMatrixImport::COLUMN_EMPLOYEE_NAME, ...$days],
        ];

        $existing = $this->existingRoster();

        foreach ($this->employees as $employee) {
            $row = [(string) $employee->employee_number, (string) $employee->full_name];

            foreach ($days as $day) {
                $row[] = $existing[$employee->id][$day] ?? '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The roster already materialized for these employees this month, rendered as
     * the cell text the importer would accept: employee id => day => "P" / "L".
     *
     * Only real schedule rows are read. Employees on the default office-hours
     * pattern have none by design, so their cells stay blank — pre-filling them
     * would turn a derived schedule into frozen manual overrides on re-upload.
     *
     * @return array<int, array<int, string>>
     */
    private function existingRoster(): array
    {
        if ($this->employees->isEmpty()) {
            return [];
        }

        $codes = $this->shifts->pluck('code', 'id');

        return EmployeeSchedule::query()
            ->whereIn('employee_id', $this->employees->modelKeys())
            ->whereBetween('work_date', [
                $this->month->startOfMonth()->toDateString(),
                $this->month->endOfMonth()->toDateString(),
            ])
            ->get(['employee_id', 'work_date', 'shift_id', 'is_day_off', 'is_wfh'])
            ->reduce(function (array $carry, EmployeeSchedule $schedule) use ($codes) {
                $day = (int) $schedule->work_date->format('j');

                if ($schedule->is_day_off || $schedule->shift_id === null) {
                    $carry[$schedule->employee_id][$day] = ScheduleMatrixImport::DAY_OFF_TOKENS[0];

                    return $carry;
                }

                $code = (string) ($codes[$schedule->shift_id] ?? '');

                if ($code === '') {
                    return $carry; // shift deactivated: leave the cell empty to be re-filled
                }

                $carry[$schedule->employee_id][$day] = $schedule->is_wfh
                    ? $code.'/'.ScheduleMatrixImport::WFH_SUFFIX
                    : $code;

                return $carry;
            }, []);
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $header = ScheduleMatrixImport::HEADER_ROW;
                $firstDataRow = ScheduleMatrixImport::FIRST_DATA_ROW;
                $daysInMonth = $this->month->daysInMonth;
                $lastRow = max($header, $firstDataRow + $this->employees->count() - 1);
                $lastColumn = Coordinate::stringFromColumnIndex(2 + $daysInMonth);

                // The period must survive as text: Excel happily turns "2026-08" into
                // a date, and the importer would then read the wrong month.
                $sheet->getCell('B1')->setValueExplicit($this->month->format('Y-m'), DataType::TYPE_STRING);

                $sheet->getStyle('A1:B1')->getFont()->setBold(true);
                $sheet->getStyle("A{$header}:{$lastColumn}{$header}")->getFont()->setBold(true);
                $sheet->getStyle("C{$header}:{$lastColumn}{$header}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(28);

                $holidays = $this->holidayDays();

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $letter = Coordinate::stringFromColumnIndex(2 + $day);
                    $sheet->getColumnDimension($letter)->setWidth(5.5);

                    // Weekends and national holidays are tinted so whoever fills the
                    // grid can see at a glance which columns are rest days.
                    if ($this->month->setDay($day)->isWeekend() || isset($holidays[$day])) {
                        $sheet->getStyle("{$letter}{$header}:{$letter}{$lastRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WEEKEND_TINT);
                    }
                }

                // Keep the employee columns and the header in view while scrolling.
                $sheet->freezePane("C{$firstDataRow}");

                if ($this->employees->isNotEmpty()) {
                    $sheet->getStyle("C{$firstDataRow}:{$lastColumn}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $this->addCodeDropdown($sheet, $firstDataRow, $lastRow, $lastColumn);
                }
            },
        ];
    }

    /**
     * An in-cell dropdown of the valid codes, so most cells can be picked instead of
     * typed. Skipped when the list exceeds Excel's inline validation limit.
     */
    private function addCodeDropdown(Worksheet $sheet, int $firstDataRow, int $lastRow, string $lastColumn): void
    {
        $options = $this->shifts->pluck('code')
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->filter()
            ->push(ScheduleMatrixImport::DAY_OFF_TOKENS[0])
            ->unique()
            ->implode(',');

        // Excel caps an inline validation list at 255 characters, quotes included.
        if ($options === '' || mb_strlen($options) > 250) {
            return;
        }

        $validation = $sheet->getCell("C{$firstDataRow}")->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Kode tidak dikenali')
            // Peringatan informasi, bukan penolakan: jam kerja boleh diketik manual
            // walau tidak ada di daftar dropdown.
            ->setError('Pilih kode shift yang tersedia, tulis jam kerjanya (contoh "08:00-17:00"), atau "L" untuk libur. Kosongkan bila jadwal hari itu tidak diubah.')
            ->setFormula1('"'.$options.'"');

        $sheet->setDataValidation("C{$firstDataRow}:{$lastColumn}{$lastRow}", $validation);
    }

    /**
     * Days of this month that fall on a holiday, for the column tinting.
     *
     * @return array<int, true>
     */
    private function holidayDays(): array
    {
        return Holiday::query()
            ->whereBetween('date', [
                $this->month->startOfMonth()->toDateString(),
                $this->month->endOfMonth()->toDateString(),
            ])
            ->pluck('date')
            ->reduce(function (array $carry, $date) {
                $carry[(int) CarbonImmutable::parse($date)->format('j')] = true;

                return $carry;
            }, []);
    }
}
