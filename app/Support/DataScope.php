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
     * Dipakai HANYA oleh halaman Absensi Harian, Jadwal Kerja dan Cuti & Izin beserta
     * tindakan di dalamnya. Modul lain — lembur, koreksi, laporan, data karyawan —
     * tetap memakai forAttendance()/forEmployees() dan tidak berubah sedikit pun.
     *
     * Yang perlu disadari: pengguna tanpa bawahan dan tanpa saklar itu tidak akan
     * melihat siapa-siapa di ketiga halaman tersebut. Itu memang maksudnya — dan
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
        // Bagi pengguna yang dipersempit ke bawahan, cakupannya adalah garis atasannya
        // — bukan lokasi/divisi. Tanpa syarat ini seorang atasan yang punya tim tapi
        // belum diberi cakupan lokasi akan disuruh "minta admin menetapkan lokasi
        // kerja", padahal timnya justru sudah ada.
        if ($this->subordinatesOnly) {
            return false;
        }

        return $this->user->hasNoDataScope($this->bypassPermission);
    }

    /** Employees this user may see. */
    public function employees(): Builder
    {
        if ($this->subordinatesOnly) {
            // Garis atasan MENGGANTIKAN cakupan lokasi/divisi, bukan menumpuk di
            // atasnya — persis seperti mode "Batasi ke bawahan" yang sudah lama ada di
            // Employee::scopeVisibleTo().
            //
            // Menumpuknya membuat bawahan yang kebetulan tercatat di divisi atau lokasi
            // lain daripada cakupan atasannya menghilang dari layar, padahal mereka
            // jelas-jelas ada di bawah garisnya. Struktur organisasi tidak selalu
            // sejajar dengan pembagian lokasi/divisi, dan yang ditanyakan halaman ini
            // adalah "siapa tim saya", bukan "siapa yang sedivisi dengan saya".
            //
            // [0] agar yang tanpa bawahan mendapat daftar kosong, bukan seluruh tabel.
            return Employee::query()->whereIn('id', $this->subordinateIds() ?: [0]);
        }

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
        if ($employee === null) {
            return false;
        }

        // Sama seperti employees(): garis atasan menggantikan cakupan lokasi/divisi,
        // jadi keanggotaan di bawah garis itu sudah cukup dan sudah final.
        if ($this->subordinatesOnly) {
            return in_array($employee->id, $this->subordinateIds(), true);
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
