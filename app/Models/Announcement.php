<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pengumuman resmi HR untuk tab Kabar. Karyawan menandainya "Saya sudah membaca";
 * HR melihat siapa yang sudah dan belum membaca dari web.
 */
class Announcement extends Model
{
    use SoftDeletes;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_DEPARTMENT = 'department';

    public const ATTACHMENT_DISK = 'local';

    public const ATTACHMENT_MAX_MB = 5;

    /** @var list<string> */
    protected $fillable = [
        'title', 'body', 'audience', 'department_id', 'attachment_path', 'attachment_name',
        'attachment_mime', 'attachment_size', 'is_pinned', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_pinned' => 'boolean',
            'attachment_size' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Pengumuman yang ditujukan ke karyawan ini (semua, atau salah satu divisinya). */
    public function scopeVisibleToEmployee(Builder $query, Employee $employee): void
    {
        $departmentIds = $employee->departmentIds();

        $query->where(function (Builder $query) use ($departmentIds): void {
            $query->where('audience', self::AUDIENCE_ALL)
                ->orWhere(fn (Builder $q) => $q->where('audience', self::AUDIENCE_DEPARTMENT)
                    ->whereIn('department_id', $departmentIds !== [] ? $departmentIds : [0]));
        });
    }

    public function isVisibleTo(Employee $employee): bool
    {
        return $this->audience === self::AUDIENCE_ALL
            || in_array($this->department_id, $employee->departmentIds(), true);
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /** Karyawan aktif yang menjadi sasaran pengumuman ini (untuk hitungan "dibaca X dari Y"). */
    public function audienceQuery(): Builder
    {
        return Employee::query()->active()
            ->when($this->audience === self::AUDIENCE_DEPARTMENT, fn (Builder $q) => $q->byDepartment($this->department_id));
    }

    public function getAudienceLabelAttribute(): string
    {
        return $this->audience === self::AUDIENCE_DEPARTMENT
            ? 'Divisi '.($this->department?->name ?? '-')
            : 'Semua karyawan';
    }
}
