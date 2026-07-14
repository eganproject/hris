<?php

use App\Support\MenuPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission dipecah per menu: dulu satu "attendance.view" membuka 12 menu sekaligus,
 * sehingga akses tidak bisa diatur per menu. Migration ini membuat permission baru
 * (<menu>.<aksi>), memindahkan hak tiap role sesuai peta di bawah — jadi tidak ada
 * role yang kehilangan akses — lalu menghapus permission lama yang sudah usang.
 */
return new class extends Migration
{
    /**
     * Permission lama => permission baru yang menggantikannya.
     *
     * @return array<string, list<string>>
     */
    private function map(): array
    {
        $attendanceMenus = [
            'attendance-daily', 'punches', 'corrections', 'overtime', 'swaps',
            'devices', 'shifts', 'holidays', 'schedule-patterns', 'schedules',
            'leave', 'leave-types', 'leave-balances',
        ];

        $reports = ['reports.attendance', 'reports.log', 'reports.leave'];
        $organizationMenus = ['branches', 'departments', 'job-positions'];

        // Aksi yang benar-benar ada pada tiap menu (lihat config/rbac.php).
        $has = fn (string $menu, string $action): bool => in_array(
            $action,
            collect(config('rbac.menus'))->collapse()->get($menu)['actions'] ?? [],
            true,
        );

        $spread = fn (array $menus, string $action): array => collect($menus)
            ->filter(fn (string $menu) => $has($menu, $action))
            ->map(fn (string $menu) => $menu.'.'.$action)
            ->values()
            ->all();

        return [
            'employees.view' => ['employees.view', 'employees.export'],
            'employees.create' => ['employees.create', 'employees.import'],
            'employees.update' => ['employees.update'],
            'employees.delete' => ['employees.delete'],

            'attendance.view' => array_merge(
                $spread($attendanceMenus, 'view'),
                $spread($reports, 'view'),
                $spread($reports, 'export'),
            ),
            'attendance.create' => $spread($attendanceMenus, 'create'),
            // Pengaturan dulu ikut attendance.update, jadi pemegangnya tetap dapat.
            'attendance.update' => array_merge($spread($attendanceMenus, 'update'), ['settings.view', 'settings.update']),
            'attendance.delete' => $spread($attendanceMenus, 'delete'),

            'organization.view' => array_merge(['organization.view'], $spread($organizationMenus, 'view')),
            'organization.create' => $spread($organizationMenus, 'create'),
            'organization.update' => $spread($organizationMenus, 'update'),
            'organization.delete' => $spread($organizationMenus, 'delete'),

            'leave.request' => ['my-leave.view'],
            'attendance.correction' => ['my-attendance.view'],
            'schedule.swap' => ['my-schedule.view'],
            'overtime.request' => ['my-overtime.view'],
        ];
    }

    public function up(): void
    {
        $guard = 'web';

        foreach (MenuPermissions::all() as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $map = $this->map();

        foreach (Role::query()->where('guard_name', $guard)->with('permissions')->get() as $role) {
            $held = $role->permissions->pluck('name')->all();
            $grant = [];

            foreach ($map as $old => $replacements) {
                if (in_array($old, $held, true)) {
                    $grant = array_merge($grant, $replacements);
                }
            }

            if ($grant !== []) {
                $role->givePermissionTo(array_unique($grant));
            }
        }

        // Selain lewat role, permission juga bisa ditempel langsung ke satu pengguna.
        // Kalau tidak ikut dipindahkan, pengguna itu kehilangan aksesnya begitu
        // permission lama dihapus.
        $this->migrateDirectUserPermissions($map, $guard);

        // Permission lama tidak lagi dipakai di rute maupun tampilan.
        Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', array_keys($map))
            ->whereNotIn('name', MenuPermissions::all())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, list<string>>  $map
     */
    private function migrateDirectUserPermissions(array $map, string $guard): void
    {
        $permissionIds = Permission::query()
            ->where('guard_name', $guard)
            ->pluck('id', 'name');

        $rows = DB::table('model_has_permissions')->get();
        $insert = [];

        foreach ($rows as $row) {
            $old = $permissionIds->search($row->permission_id);

            if ($old === false || ! isset($map[$old])) {
                continue;
            }

            foreach ($map[$old] as $new) {
                if (! isset($permissionIds[$new])) {
                    continue;
                }

                $insert[] = [
                    'permission_id' => $permissionIds[$new],
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ];
            }
        }

        foreach (array_chunk(array_values(array_unique($insert, SORT_REGULAR)), 100) as $chunk) {
            DB::table('model_has_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Peta baliknya ambigu (satu permission lama = banyak menu), jadi tidak dibalik.
    }
};
