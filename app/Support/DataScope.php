<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * One place to answer "which employees may this user work with?", so every module
 * (absensi, jadwal, cuti, lembur, koreksi, laporan) applies the same rule instead
 * of re-deriving it. See Employee::scopeVisibleTo() for the rule itself.
 */
class DataScope
{
    private function __construct(
        private readonly User $user,
        private readonly string $bypassPermission,
    ) {}

    public static function forAttendance(User $user): self
    {
        return new self($user, User::SCOPE_BYPASS_ATTENDANCE);
    }

    public static function forEmployees(User $user): self
    {
        return new self($user, User::SCOPE_BYPASS_EMPLOYEES);
    }

    /** The user sees everything: no filtering needed anywhere. */
    public function isUnrestricted(): bool
    {
        return $this->user->seesAllData($this->bypassPermission);
    }

    /** Neither a bypass nor any scope: the user sees nobody until an admin sets one. */
    public function isEmpty(): bool
    {
        return $this->user->hasNoDataScope($this->bypassPermission);
    }

    /** Employees this user may see. */
    public function employees(): Builder
    {
        return Employee::query()->visibleTo($this->user, $this->bypassPermission);
    }

    /**
     * Constrain any query that hangs off an employee (attendance, leave, punches, …).
     * A no-op for unrestricted users, so the common case stays a plain query.
     */
    public function constrain(Builder $query, string $column = 'employee_id'): void
    {
        if ($this->isUnrestricted()) {
            return;
        }

        $query->whereIn($column, $this->employees()->select('id'));
    }

    public function allows(?Employee $employee): bool
    {
        return $employee !== null && $employee->isVisibleTo($this->user, $this->bypassPermission);
    }

    /** 403 unless the employee is inside this scope. */
    public function authorize(?Employee $employee): void
    {
        abort_unless($this->allows($employee), 403);
    }

    /** Active work locations the user may pick from (all of them when unrestricted). */
    public function branches(): Collection
    {
        $ids = $this->isUnrestricted() ? [] : $this->user->accessBranchIds();

        return Branch::query()
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    /** Active divisions the user may pick from (all of them when unrestricted). */
    public function departments(): Collection
    {
        $ids = $this->isUnrestricted() ? [] : $this->user->accessDepartmentIds();

        return Department::query()
            ->where('is_active', true)
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }
}
