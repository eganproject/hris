<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EmployeeEvent extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'type',
    'description',
    'occurred_at',
    'causer_id',
    'properties',
    ];

    /**
     * type => [label, tone] where tone maps to the <x-status-badge> colour set.
     */
    public const TYPE_META = [
        'joined' => ['label' => 'Bergabung', 'tone' => 'success'],
        'contract_created' => ['label' => 'Kontrak dibuat', 'tone' => 'info'],
        'contract_renewed' => ['label' => 'Kontrak diperpanjang', 'tone' => 'info'],
        'contract_ended' => ['label' => 'Kontrak berakhir', 'tone' => 'warning'],
        'exited' => ['label' => 'Karyawan keluar', 'tone' => 'danger'],
        'reactivated' => ['label' => 'Diaktifkan kembali', 'tone' => 'success'],
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function getTitleAttribute(): string
    {
        return self::TYPE_META[$this->type]['label'] ?? str($this->type)->headline()->toString();
    }

    public function getToneAttribute(): string
    {
        return self::TYPE_META[$this->type]['tone'] ?? 'neutral';
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'properties' => 'array',
        ];
    }
}
