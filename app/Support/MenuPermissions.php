<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Reads the menu catalog (config/rbac.php) — the single source of truth for what a
 * role can be given access to. Permission names are "<menu key>.<action>" unless the
 * menu overrides them (the data-scope rows do).
 */
class MenuPermissions
{
    /**
     * The permission name for one action on one menu.
     */
    public static function name(string $menuKey, string $action, array $menu = []): string
    {
        return $menu['permissions'][$action] ?? $menuKey.'.'.$action;
    }

    /**
     * Every permission the system knows about, in catalog order.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::groups() as $menus) {
            foreach ($menus as $key => $menu) {
                foreach ($menu['actions'] as $action) {
                    $names[] = self::name($key, $action, $menu);
                }
            }
        }

        return $names;
    }

    /**
     * The catalog grouped as shown in the matrix: group => [key => menu].
     *
     * @return array<string, array<string, array{label: string, actions: list<string>, permissions?: array<string, string>}>>
     */
    public static function groups(): array
    {
        return config('rbac.menus', []);
    }

    /**
     * Rows for the Kontrol Akses matrix: each row carries its label plus, per action
     * column, either the permission name or null when the action does not apply.
     *
     * @return Collection<string, Collection<int, array{key: string, label: string, cells: array<string, ?string>}>>
     */
    public static function matrix(): Collection
    {
        $actions = array_keys(config('rbac.actions', []));

        return collect(self::groups())->map(
            fn (array $menus) => collect($menus)->map(function (array $menu, string $key) use ($actions) {
                $cells = [];

                foreach ($actions as $action) {
                    $cells[$action] = in_array($action, $menu['actions'], true)
                        ? self::name($key, $action, $menu)
                        : null;
                }

                return ['key' => $key, 'label' => $menu['label'], 'cells' => $cells];
            })->values(),
        );
    }
}
