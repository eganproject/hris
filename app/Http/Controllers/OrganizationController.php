<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __invoke(): View
    {
        $branches = Branch::query()
            ->with([
                'departments' => fn ($query) => $query
                    ->withCount(['employees as active_employees_count' => fn ($query) => $query->active()])
                    ->orderBy('name'),
            ])
            ->withCount(['employees', 'employees as active_employees_count' => fn ($query) => $query->active()])
            ->where('is_active', true)
            ->orderBy('city')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->with([
                'branches' => fn ($query) => $query
                    ->wherePivot('is_active', true)
                    ->where('branches.is_active', true)
                    ->orderBy('city')
                    ->orderBy('name'),
            ])
            ->withCount(['employees', 'employees as active_employees_count' => fn ($query) => $query->active(), 'jobPositions'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('organization.index', [
            'branches' => $branches,
            'departments' => $departments,
            'summary' => [
                'locations' => $branches->count(),
                'warehouses' => $branches->where('type', 'warehouse')->count(),
                'departments' => $departments->count(),
                'active_employees' => Employee::query()->active()->count(),
            ],
        ]);
    }
}
