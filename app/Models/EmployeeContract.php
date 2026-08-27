<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EmployeeContract extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'status',
        'notes',
        'document_path',
        'document_name',
        'document_mime',
        'document_size',
    ];

    /** Disk privat: dokumen kontrak hanya boleh keluar lewat rute berotorisasi. */
    public const DOCUMENT_DISK = 'local';

    /** Batas ukuran unggahan dokumen kontrak, dalam MB. */
    public const DOCUMENT_MAX_MB = 5;

    public const STATUS_LABELS = [
        'active' => 'Aktif',
        'completed' => 'Selesai Sesuai Masa Kontrak',
        'ended_early' => 'Diakhiri Lebih Awal',
        'renewed' => 'Diperpanjang',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa / Belum Diperbarui',
    ];

    /**
     * Contract statuses that mean the working relationship is being closed. Setting
     * the current contract to any of these during an edit triggers the exit flow.
     * ("renewed" is excluded: the employee continues under a renewed contract.)
     */
    public const CLOSING_STATUSES = ['completed', 'ended_early', 'expired', 'cancelled'];

    /**
     * Kenapa kontrak ini TIDAK boleh dihapus, atau null bila boleh.
     *
     * Hapus di sini hanya untuk membersihkan baris sampah — duplikat hasil salah
     * input atau impor. Riwayat kontrak yang sah tidak pernah dihapus; kontrak yang
     * sudah tidak berlaku ditutup lewat statusnya (Selesai/Dibatalkan/dst).
     *
     * Dua pagarnya menutup satu kondisi yang sama: karyawan yang "menggantung" —
     * berstatus Aktif tapi tanpa kontrak aktif. Formulir karyawan tidak pernah
     * mengizinkan kondisi itu, dan DeactivateExpiredContracts menyaring lewat
     * whereHas('contracts'), jadi karyawan tanpa kontrak tidak akan pernah
     * dinonaktifkan otomatis — ia aktif selamanya tanpa ada yang menyadarinya.
     */
    public function deletionBlocker(): ?string
    {
        $employee = $this->employee;

        if (! $employee) {
            return null; // kontrak yatim: tidak ada yang bisa menggantung.
        }

        // Lewat koleksi, bukan query: daftar kontrak memanggil ini sekali per baris,
        // jadi controller cukup meng-eager-load employee.contracts dan seluruh
        // halaman tetap satu query — bukan dua query tambahan per baris.
        $siblings = $employee->contracts->reject(fn (self $other) => $other->getKey() === $this->getKey());

        if ($siblings->isEmpty()) {
            return 'Ini satu-satunya kontrak karyawan tersebut. Karyawan tanpa kontrak tidak akan pernah dinonaktifkan otomatis saat masa kerjanya berakhir.';
        }

        if (! $employee->isInactive() && $this->status === 'active'
            && $siblings->every(fn (self $other) => $other->status !== 'active')) {
            return 'Ini satu-satunya kontrak aktif milik karyawan yang masih aktif. Tutup atau ganti kontraknya lebih dulu.';
        }

        return null;
    }

    public function isDeletable(): bool
    {
        return $this->deletionBlocker() === null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function hasDocument(): bool
    {
        return $this->document_path !== null;
    }

    public function documentSizeLabel(): string
    {
        $bytes = (int) $this->document_size;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * Simpan berkas unggahan dan kembalikan kolom-kolomnya. Disimpan per karyawan
     * supaya berkas satu orang mudah ditelusuri di disk.
     *
     * @return array<string, mixed>
     */
    public static function documentColumnsFor(UploadedFile $file, int $employeeId): array
    {
        return [
            'document_path' => $file->store("contract-documents/{$employeeId}", self::DOCUMENT_DISK),
            'document_name' => $file->getClientOriginalName(),
            'document_mime' => $file->getClientMimeType(),
            'document_size' => $file->getSize(),
        ];
    }

    /** @return array<string, null> */
    public static function emptyDocumentColumns(): array
    {
        return [
            'document_path' => null,
            'document_name' => null,
            'document_mime' => null,
            'document_size' => null,
        ];
    }

    /**
     * Buang berkasnya dari disk. Dipanggil saat dokumen diganti, dihapus, atau baris
     * kontraknya dihapus — tanpa ini disk terus menumpuk berkas yatim yang tidak lagi
     * bisa dijangkau lewat rute mana pun.
     */
    public function deleteDocumentFile(): void
    {
        if ($this->document_path) {
            Storage::disk(self::DOCUMENT_DISK)->delete($this->document_path);
        }
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query
            ->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    /**
     * Kontrak yang periodenya beririsan dengan kontrak lain milik karyawan yang sama.
     *
     * Inilah tanda duplikat yang sebenarnya. Sekadar "punya lebih dari satu kontrak"
     * tidak berguna sebagai penyaring: karyawan lama yang kontraknya berkali-kali
     * diperpanjang memang punya banyak kontrak, dan itu wajar — periodenya berurutan,
     * tidak bertindihan. Yang tidak wajar adalah dua kontrak yang berlaku pada rentang
     * tanggal yang sama; itu tidak pernah benar, dan hanya muncul dari salah input,
     * impor ganda, atau bug penyimpanan.
     *
     * Kontrak tanpa tanggal selesai (PKWTT) dianggap berlaku sampai kapan pun.
     */
    public function scopeOverlapping(Builder $query): void
    {
        $query
            ->whereNotNull('employee_id')
            ->whereNotNull('start_date')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('employee_contracts as sibling')
                    ->whereColumn('sibling.employee_id', 'employee_contracts.employee_id')
                    ->whereColumn('sibling.id', '!=', 'employee_contracts.id')
                    ->whereNotNull('sibling.start_date')
                    ->whereRaw("sibling.start_date <= coalesce(employee_contracts.end_date, '9999-12-31')")
                    ->whereRaw("coalesce(sibling.end_date, '9999-12-31') >= employee_contracts.start_date");
            });
    }

    /**
     * Contracts still marked "active" whose end date has already passed without
     * being renewed or closed out — the state that needs HR attention.
     */
    public function scopeLapsed(Builder $query): void
    {
        $query
            ->active()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString());
    }

    public function getRemainingDaysAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->end_date, false);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? str($this->status)->headline()->toString();
    }

    /**
     * A stored "active" contract whose end date has already passed without being
     * renewed or closed out. This is the state that used to silently keep showing
     * as "Aktif" even though the contract period was already over.
     */
    public function getIsLapsedAttribute(): bool
    {
        return $this->status === 'active'
            && $this->end_date !== null
            && $this->remaining_days !== null
            && $this->remaining_days < 0;
    }

    /**
     * The status that should actually be shown to a human, taking the calendar
     * into account. For non-active stored statuses we keep the stored label; for
     * active contracts we derive open-ended / dated / expiring / lapsed.
     */
    public function getEffectiveStatusLabelAttribute(): string
    {
        if ($this->status !== 'active') {
            return $this->status_label;
        }

        if ($this->end_date === null) {
            return 'Aktif · tanpa batas waktu';
        }

        $remaining = $this->remaining_days;

        if ($remaining < 0) {
            return 'Kedaluwarsa · berakhir '.abs($remaining).' hari lalu (belum diperbarui)';
        }

        if ($remaining === 0) {
            return 'Berakhir hari ini';
        }

        if ($remaining <= 30) {
            return 'Akan berakhir · '.$remaining.' hari lagi';
        }

        return 'Aktif · s/d '.$this->end_date->format('d M Y');
    }

    public function getEffectiveStatusToneAttribute(): string
    {
        if ($this->status === 'active') {
            if ($this->end_date === null) {
                return 'success';
            }

            $remaining = $this->remaining_days;

            return match (true) {
                $remaining < 0 => 'danger',
                $remaining <= 30 => 'warning',
                default => 'success',
            };
        }

        return match ($this->status) {
            'renewed', 'completed' => 'info',
            'expired' => 'danger',
            'ended_early', 'cancelled' => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * @return array<int, string>
     */
    public static function closingStatuses(): array
    {
        return self::CLOSING_STATUSES;
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'document_size' => 'integer',
        ];
    }
}
