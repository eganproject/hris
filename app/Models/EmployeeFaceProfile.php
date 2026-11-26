<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data wajah satu karyawan untuk tablet absensi: pola angka wajah (embedding)
 * dari 3 foto pendaftaran, 1 foto depan untuk HR, persetujuan UU PDP, dan PIN
 * cadangan. Foto mentah lainnya tidak disimpan.
 */
class EmployeeFaceProfile extends Model
{
    /** @var list<string> */
    protected $fillable = ['employee_id'];

    /** @var list<string> */
    protected $hidden = ['embeddings', 'pin_hash'];

    protected function casts(): array
    {
        return [
            'embeddings' => 'array',
            'embedding_dim' => 'integer',
            'consented_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'pin_set_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enrolledTablet(): BelongsTo
    {
        return $this->belongsTo(AttendanceTablet::class, 'enrolled_tablet_id');
    }

    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function hasFace(): bool
    {
        return ! empty($this->embeddings);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }
}
