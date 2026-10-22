<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * Siapa boleh memberikan role apa.
 *
 * Aturannya: seseorang hanya boleh memberikan role yang seluruh izinnya sudah ia
 * pegang sendiri, dan role superadmin hanya boleh diberikan oleh superadmin. Tanpa
 * aturan ini, pemegang hak "ubah karyawan" bisa membuat akun superadmin lewat form
 * karyawan, import, atau role default jabatan — melewati Kontrol Akses sepenuhnya.
 */
class RoleGuard
{
    public static function isProtected(Role|string $role): bool
    {
        $name = $role instanceof Role ? $role->name : $role;

        return in_array($name, User::SUPER_ADMIN_ROLES, true);
    }

    public static function canAssign(User $actor, ?Role $role): bool
    {
        if ($role === null) {
            return true;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (self::isProtected($role)) {
            return false;
        }

        $actorPermissions = $actor->getAllPermissions()->pluck('name')->all();

        return $role->permissions->pluck('name')->diff($actorPermissions)->isEmpty();
    }

    /**
     * Role yang boleh ditawarkan kepada pengguna ini di form dan import.
     *
     * @return Collection<int, Role>
     */
    public static function assignableRoles(User $actor): Collection
    {
        return Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => self::canAssign($actor, $role))
            ->values();
    }

    /**
     * Akun login milik orang lain yang tidak boleh diubah (email, password, role)
     * oleh pengguna ini: akun superadmin, kecuali pengubahnya juga superadmin.
     */
    public static function canManageAccount(User $actor, ?User $target): bool
    {
        if ($target === null || $actor->isSuperAdmin()) {
            return true;
        }

        return ! $target->isSuperAdmin();
    }

    /**
     * Aturan validasi untuk kolom berisi id role (mis. role default jabatan). Nilai
     * yang tidak berubah dibiarkan, supaya menyunting nama jabatan tidak ditolak
     * hanya karena role default-nya dulu dipasang oleh admin lain.
     */
    public static function assignableRule(User $actor, int|string|null $currentRoleId = null): Closure
    {
        $currentRoleId = $currentRoleId === null ? null : (int) $currentRoleId;

        return function (string $attribute, mixed $value, Closure $fail) use ($actor, $currentRoleId): void {
            if ($value === null || $value === '' || (int) $value === $currentRoleId) {
                return;
            }

            $role = Role::query()->find((int) $value);

            if ($role && ! self::canAssign($actor, $role)) {
                $fail("Anda tidak berwenang memberikan role \"{$role->name}\".");
            }
        };
    }
}
