<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Postingan karyawan di tab Kabar. Audiens "division" hanya terlihat oleh rekan
 * yang berbagi divisi dengan penulis (divisi utama penulis saat posting).
 * Postingan yang disembunyikan HR (hidden_at) hilang dari feed semua orang
 * kecuali penulisnya sendiri.
 */
class Post extends Model
{
    use SoftDeletes;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_DIVISION = 'division';

    public const IMAGE_DISK = 'local';

    public const IMAGE_MAX_MB = 5;

    public const BODY_MAX = 2000;

    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'body', 'audience', 'department_id', 'image_path', 'image_mime',
        'hidden_at', 'hidden_by', 'hidden_reason',
    ];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    public function hider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * Postingan yang boleh tampil di feed karyawan ini: audiens sesuai, dan tidak
     * disembunyikan (kecuali miliknya sendiri, supaya penulis tahu nasibnya).
     */
    public function scopeVisibleToEmployee(Builder $query, Employee $employee): void
    {
        $departmentIds = $employee->departmentIds();

        $query->where(function (Builder $query) use ($departmentIds, $employee): void {
            $query->where('audience', self::AUDIENCE_ALL)
                ->orWhere(fn (Builder $q) => $q->where('audience', self::AUDIENCE_DIVISION)
                    ->whereIn('department_id', $departmentIds !== [] ? $departmentIds : [0]))
                ->orWhere('employee_id', $employee->id);
        })->where(fn (Builder $q) => $q->whereNull('hidden_at')->orWhere('employee_id', $employee->id));
    }

    public function isVisibleTo(Employee $employee): bool
    {
        if ($this->employee_id === $employee->id) {
            return true;
        }

        if ($this->isHidden()) {
            return false;
        }

        return $this->audience === self::AUDIENCE_ALL
            || in_array($this->department_id, $employee->departmentIds(), true);
    }
}
