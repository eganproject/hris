<?php

namespace App\Actions\Assets;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AssetTransactionType;
use App\Exceptions\AssetCustodyException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ApprovalNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyerahkan sebuah aset kepada seorang karyawan.
 *
 * Seluruhnya berjalan di dalam satu transaksi dengan baris asetnya dikunci
 * (lockForUpdate). Tanpa kunci itu, dua petugas yang menyerahkan aset yang sama pada
 * saat bersamaan akan sama-sama membaca "tersedia" dan sama-sama berhasil — dan aset
 * itu tercatat dipegang dua orang sekaligus, keadaan yang tidak bisa diperbaiki
 * belakangan tanpa menebak siapa yang sebenarnya membawanya pulang.
 */
class AssignAsset
{
    public function __construct(private readonly ApprovalNotifier $notifier) {}

    /**
     * @param  array{employee_id: int, assigned_at?: string|null, expected_return_at?: string|null, condition_out: string, purpose?: string|null, notes?: string|null}  $data
     */
    public function handle(Asset $asset, array $data, User $actor): AssetAssignment
    {
        $assignment = DB::transaction(function () use ($asset, $data, $actor): AssetAssignment {
            $locked = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            if (! $locked->status?->isAssignable()) {
                throw new AssetCustodyException(
                    "Aset {$locked->asset_code} berstatus \"{$locked->status_label}\", jadi belum bisa diserahkan. Hanya aset berstatus \"Tersedia\" yang bisa diserahkan.",
                );
            }

            if ($locked->assignments()->open()->exists()) {
                throw new AssetCustodyException(
                    "Aset {$locked->asset_code} sudah tercatat dipegang orang lain. Muat ulang halamannya untuk melihat keadaan terbaru.",
                );
            }

            $employee = Employee::query()->findOrFail($data['employee_id']);

            if ($employee->isInactive()) {
                throw new AssetCustodyException(
                    "{$employee->full_name} sudah tidak aktif, jadi tidak bisa menerima aset baru.",
                );
            }

            $condition = AssetCondition::from($data['condition_out']);

            if (! $condition->isServiceable()) {
                throw new AssetCustodyException(
                    "Aset berkondisi \"{$condition->label()}\" tidak layak diserahkan. Perbaiki dulu, atau catat sebagai perawatan.",
                );
            }

            $assignedAt = isset($data['assigned_at']) && $data['assigned_at']
                ? Carbon::parse($data['assigned_at'])
                : now();

            $assignment = $locked->assignments()->create([
                'employee_id' => $employee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => $assignedAt,
                'expected_return_at' => $data['expected_return_at'] ?? null,
                'condition_out' => $condition->value,
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $previousStatus = $locked->status;

            $locked->update([
                'status' => AssetStatus::Assigned->value,
                'condition' => $condition->value,
            ]);

            $locked->transactions()->create([
                'assignment_id' => $assignment->id,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => AssetTransactionType::Assigned->value,
                'from_status' => $previousStatus?->value,
                'to_status' => AssetStatus::Assigned->value,
                'to_employee_id' => $employee->id,
                'to_label' => $employee->full_name,
                'condition' => $condition->value,
                'occurred_at' => $assignedAt,
                'notes' => $data['purpose'] ?? null,
            ]);

            ActivityLogger::log(
                module: 'assets',
                event: 'assigned',
                description: "Menyerahkan aset {$locked->asset_code} kepada {$employee->full_name}.",
                subject: $locked,
                properties: ['employee' => $employee->full_name, 'condition' => $condition->value],
            );

            return $assignment;
        });

        // Di luar transaksi: notifikasi baru dikirim setelah penyerahannya benar-benar
        // tersimpan, supaya karyawan tidak pernah diberi tahu soal serah-terima yang
        // ternyata gagal di detik terakhir.
        $this->notifier->assetAssigned($assignment);

        return $assignment;
    }
}
