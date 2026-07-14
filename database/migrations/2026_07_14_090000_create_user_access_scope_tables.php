<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Per-user data scope: which work locations and which divisions a user is allowed
 * to see. Empty on one axis means "semua" for that axis (a user scoped to Surabaya
 * with no division rows sees every division in Surabaya). Users holding the
 * "lihat semua" permission bypass both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
        });

        Schema::create('user_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'department_id']);
        });

        // The roles that manage the whole company keep seeing everything: without the
        // bypass they would suddenly be scoped to nothing on the next request.
        $bypass = [User::SCOPE_BYPASS_EMPLOYEES, User::SCOPE_BYPASS_ATTENDANCE];

        foreach ($bypass as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['superadmin', 'super-admin', 'hr-manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($bypass));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_department');
        Schema::dropIfExists('user_branch');
    }
};
