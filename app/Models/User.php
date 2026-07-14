<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Holding one of these means the user's data scope is not applied at all: they
     * see every work location and every division. HR pusat & superadmin have them;
     * an HR cabang does not.
     */
    public const SCOPE_BYPASS_EMPLOYEES = 'employees.view.all';

    public const SCOPE_BYPASS_ATTENDANCE = 'attendance.view.all';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Work locations this user may see. Empty = every location (still narrowed by
     * accessDepartments). Not named "scope…": that prefix is reserved by Eloquent
     * for local query scopes.
     */
    public function accessBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch')->withTimestamps();
    }

    /** Divisions this user may see. Empty = every division (within accessBranches). */
    public function accessDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_department')->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function accessBranchIds(): array
    {
        return $this->accessBranches()->pluck('branches.id')->all();
    }

    /**
     * @return list<int>
     */
    public function accessDepartmentIds(): array
    {
        return $this->accessDepartments()->pluck('departments.id')->all();
    }

    /** True when the given data domain is not limited by this user's scope. */
    public function seesAllData(string $bypassPermission): bool
    {
        return $this->can($bypassPermission);
    }

    /**
     * A user who is neither allowed to see everything nor given any scope has no
     * data to work with — the scope has simply not been set up for them yet.
     */
    public function hasNoDataScope(string $bypassPermission): bool
    {
        return ! $this->seesAllData($bypassPermission)
            && $this->accessBranchIds() === []
            && $this->accessDepartmentIds() === [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
