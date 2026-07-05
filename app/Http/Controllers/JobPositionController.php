<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobPositionRequest;
use App\Models\Department;
use App\Models\JobPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class JobPositionController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $jobPositions = JobPosition::query()
            ->with(['activeDepartments', 'defaultRole'])
            ->withCount('employees')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('level', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('organization.job-positions.index', ['jobPositions' => $jobPositions, 'filters' => $request->only('search'), 'perPage' => $perPage]);
    }

    public function create(): View
    {
        return view('organization.job-positions.create', $this->formOptions(new JobPosition(['is_active' => true])));
    }

    public function store(JobPositionRequest $request): RedirectResponse
    {
        $jobPosition = JobPosition::query()->create($request->payload());
        $this->syncDepartments($jobPosition, $request->departmentIds());

        return redirect()->route('organization.job-positions.index')->with('status', 'Jabatan berhasil dibuat.');
    }

    public function edit(JobPosition $jobPosition): View
    {
        $jobPosition->load('activeDepartments');

        return view('organization.job-positions.edit', $this->formOptions($jobPosition));
    }

    public function update(JobPositionRequest $request, JobPosition $jobPosition): RedirectResponse
    {
        $jobPosition->update($request->payload());
        $this->syncDepartments($jobPosition, $request->departmentIds());

        return redirect()->route('organization.job-positions.index')->with('status', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(JobPosition $jobPosition): RedirectResponse
    {
        $jobPosition->delete();

        return redirect()->route('organization.job-positions.index')->with('status', 'Jabatan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(JobPosition $jobPosition): array
    {
        return [
            'jobPosition' => $jobPosition,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
        ];
    }

    /**
     * @param array<int, int> $departmentIds
     */
    private function syncDepartments(JobPosition $jobPosition, array $departmentIds): void
    {
        $jobPosition->departments()->sync(
            collect($departmentIds)
                ->mapWithKeys(fn (int $departmentId): array => [$departmentId => ['is_active' => true]])
                ->all(),
        );
    }
}
