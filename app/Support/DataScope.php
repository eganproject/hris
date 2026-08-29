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
    /**
     * Id bawahan, dihitung sekali per instance: menurunkannya menyapu seluruh tabel
     * karyawan, dan satu halaman memanggil employees() berkali-kali.
     *
     * @var list<int>|null
     */
    private ?array $subordinateIds = null;

    private function __construct(
        private readonly User $user,
        private readonly string $bypassPermission,
        private readonly bool $subordinatesOnly = false,
    ) {}

    public static function forAttendance(User $user): self
    {
        return new self($user, User::SCOPE_BYPASS_ATTENDANCE);
    }

    /**
     * Cakupan absensi yang dipersempit ke garis atasan pengguna: seorang pengguna
     * hanya berurusan dengan bawahannya sendiri, kecuali ia dikecualikan lewat saklar
     * "Lihat semua karyawan" di Kontrol Akses (superadmin selalu dikecualikan).
     *
     * Dipakai HANYA oleh halaman Absensi Harian dan Jadwal Kerja beserta tindakan di
     * dalamnya. Modul lain — cuti, lembur, koreksi, laporan, data karyawan — tetap
     * memakai forAttendance()/forEmployees() dan tidak berubah sedikit pun.
     *
     * Yang perlu disadari: pengguna tanpa bawahan dan tanpa saklar itu tidak akan
     * melihat siapa-siapa di kedua halaman tersebut. Itu memang maksudnya — dan
     * jalan keluarnya ada di Kontrol Akses, bukan pada pengecualian diam-diam di sini.
     */
    public static function forTeam(User $user): self
    {
        return new self($user, User::SCOPE_BYPASS_ATTENDANCE, ! $user->bypassesTeamScope());
    }

    /** Apakah cakupan ini sedang dipersempit ke bawahan saja. */
    public function isTeamOnly(): bool
    {
        return $this->subordinatesOnly;
    }

    /** Pengguna dipersempit ke bawahannya, tapi tidak punya satu pun bawahan. */
    public function hasNoTeam(): bool
    {
        return $this->subordinatesOnly && $this->subordinateIds() === [];
    }

    /** @return list<int> */
    private function subordinateIds(): array
    {
        return $this->subordinateIds ??= $this->user->subordinateEmployeeIds();
    }

    public static function forEmployees(User $user): self
    {
        return new self($user, User::SCOPE_BYPASS_EMPLOYEES);
    }

    /** The user sees everything: no filtering needed anywhere. */
    public function isUnrestricted(): bool
    {
        // Pembatasan ke bawahan berlaku di ATAS hak melihat semua data: tanpa syarat
        // ini, constrain() dan authorize() jadi tanpa efek bagi pemegang
        // attendance.view.all dan pembatasannya hanya tampak di daftarnya saja.
        return ! $this->subordinatesOnly && $this->user->seesAllData($this->bypassPermission);
    }

    /** Neither a bypass nor any scope: the user sees nobody until an admin sets one. */
    public function isEmpty(): bool
    {
        return $this->user->hasNoDataScope($this->bypassPermission);
    }

    /** Employees this user may see. */
    public function employees(): Builder
    {
        return Employee::query()
            ->visibleTo($this->user, $this->bypassPermission)
            // [0] agar yang tanpa bawahan mendapat daftar kosong, bukan seluruh tabel.
            ->when($this->subordinatesOnly, fn (Builder $query) => $query
                ->whereIn('id', $this->subordinateIds() ?: [0]));
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
        if ($employee === null) {
            return false;
        }

        if ($this->subordinatesOnly && ! in_array($employee->id, $this->subordinateIds(), true)) {
            return false;
        }

        return $employee->isVisibleTo($this->user, $this->bypassPermission);
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
