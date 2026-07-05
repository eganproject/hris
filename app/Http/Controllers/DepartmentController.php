<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $departments = Department::query()
            ->withCount(['branches', 'jobPositions', 'employees', 'employees as active_employees_count' => fn ($query) => $query->active()])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('organization.departments.index', ['departments' => $departments, 'filters' => $request->only('search'), 'perPage' => $perPage]);
    }

    public function create(): View
    {
        return view('organization.departments.create', ['department' => new Department(['is_active' => true])]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->payload());

        return redirect()->route('organization.departments.index')->with('status', 'Divisi berhasil dibuat.');
    }

    public function edit(Department $department): View
    {
        return view('organization.departments.edit', ['department' => $department]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->payload());

        return redirect()->route('organization.departments.index')->with('status', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('organization.departments.index')->with('status', 'Divisi berhasil dihapus.');
    }
}
