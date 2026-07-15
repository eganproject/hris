<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global quick-search (command palette) — finds employees the user is allowed to
 * see from anywhere in the app. Always scoped by DataScope, so it never leaks names
 * outside the user's branch/division/subordinate reach.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['employees' => []]);
        }

        $employees = DataScope::forEmployees($request->user())
            ->employees()
            ->where(function ($query) use ($term): void {
                $query->where('full_name', 'like', "%{$term}%")
                    ->orWhere('employee_number', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->with(['branch', 'jobPosition'])
            ->orderBy('full_name')
            ->limit(8)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'number' => $employee->employee_number,
                'position' => $employee->jobPosition?->name,
                'branch' => $employee->branch?->name,
                'active' => ! $employee->isInactive(),
                'url' => route('employees.show', $employee),
            ])
            ->all();

        return response()->json(['employees' => $employees]);
    }
}
