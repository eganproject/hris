<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The monthly roster template: a "Jadwal" sheet shaped exactly as
 * ScheduleMatrixImport expects it, pre-filled with the employees in the caller's
 * current view and with whatever roster they already have for that month — so the
 * file doubles as an editor for an existing month, not just a blank form.
 *
 * A second "Petunjuk" sheet documents the shift codes and the fill rules, kept in
 * step with the importer through its shared constants.
 */
class ScheduleTemplateExport implements WithMultipleSheets
{
    /**
     * @param  EloquentCollection<int, Employee>  $employees  already scoped/filtered by the caller
     */
    public function __construct(
        private readonly CarbonImmutable $month,
        private readonly EloquentCollection $employees,
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        $shifts = Shift::query()->where('is_active', true)->orderBy('start_time')->get();

        return [
            new ScheduleTemplateDataSheet($this->month, $this->employees, $shifts),
            new ScheduleTemplateGuideSheet($this->month, $shifts),
        ];
    }
}
