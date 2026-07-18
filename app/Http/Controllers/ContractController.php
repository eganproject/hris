<?php

namespace App\Http\Controllers;

use App\Exports\ContractsExport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Read-only cross-employee view of contracts, centred on the "about to end"
 * question HR keeps asking. All actions (renew, edit) still live on the employee
 * record; this page only surfaces and filters. Everything is scoped to the
 * employees the signed-in user is allowed to see.
 */
class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['filter', 'type', 'branch_id', 'department_id', 'search']);
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        // A fresh base query per use so each summary card counts independently.
        $base = fn (): Builder => $this->baseQuery($filters, $user);

        $contracts = $this->applyRange($base(), $filters['filter'] ?? 'all')
            ->with(['employee.branch', 'employee.departments'])
            // Soonest-ending first; open-ended (no end date) contracts sink to the bottom.
            ->orderByRaw('end_date is null')
            ->orderBy('end_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('employees.contracts.index', [
            'contracts' => $contracts,
            'branches' => $this->scopedBranches($user),
            'departments' => $this->scopedDepartments($user),
            'filters' => $filters,
            'perPage' => $perPage,
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
            'summary' => [
                'total' => $base()->count(),
                'active' => $base()->active()->count(),
                'expiring_30' => $base()->expiringWithin(30)->count(),
                'expiring_60' => $base()->expiringWithin(60)->count(),
                'expiring_90' => $base()->expiringWithin(90)->count(),
                'expired' => $base()->lapsed()->count(),
            ],
        ]);
    }

    /**
     * Export the contract list (honouring the current filters) to .xlsx.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['filter', 'type', 'branch_id', 'department_id', 'search']);

        return Excel::download(
            new ContractsExport($filters, $request->user()),
            'kontrak-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Contracts belonging to employees inside the user's scope, narrowed by the
     * location / division / type / search filters (but NOT the range preset).
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters, User $user): Builder
    {
        return EmployeeContract::query()
            ->whereHas('employee', fn (Builder $query) => $query
                ->visibleTo($user)
                ->byBranch($filters['branch_id'] ?? null)
                ->byDepartment($filters['department_id'] ?? null))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('contract_type', $type))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn (Builder $employee) => $employee
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%"));
                });
            });
    }

    private function applyRange(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'active' => $query->active(),
            'expiring_30' => $query->expiringWithin(30),
            'expiring_60' => $query->expiringWithin(60),
            'expiring_90' => $query->expiringWithin(90),
            'expired' => $query->lapsed(),
            default => $query,
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Branch>
     */
    private function scopedBranches(User $user)
    {
        $branchIds = $user->seesAllData(User::SCOPE_BYPASS_EMPLOYEES) ? [] : $user->accessBranchIds();

        return Branch::query()
            ->where('is_active', true)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('id', $branchIds))
            ->orderBy('city')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Department>
     */
    private function scopedDepartments(User $user)
    {
        $departmentIds = $user->seesAllData(User::SCOPE_BYPASS_EMPLOYEES) ? [] : $user->accessDepartmentIds();

        return Department::query()
            ->where('is_active', true)
            ->when($departmentIds !== [], fn ($query) => $query->whereIn('id', $departmentIds))
            ->orderBy('name')
            ->get();
    }
}
