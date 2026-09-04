<?php

namespace App\Actions\Assets;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AssetTransactionType;
use App\Exceptions\AssetCustodyException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menerima kembali aset dari karyawan.
 *
 * Pengembalian MENUTUP masa pegang, bukan menghapusnya: returned_at dan kondisi saat
 * kembali diisi pada baris yang sama, sehingga pertanyaan "siapa yang memegang barang
 * ini bulan lalu" tetap punya jawaban bertahun-tahun kemudian.
 *
 * Status aset berikutnya ditentukan hasil pemeriksaan, bukan diasumsikan kembali
 * "tersedia": barang yang pulang dalam keadaan rusak masuk antrean perawatan, dan
 * yang tidak layak lagi berhenti di "tidak dipakai" — kalau semuanya langsung
 * dinyatakan tersedia, barang rusak akan diserahkan lagi ke orang berikutnya.
 */
class ReturnAsset
{
    /** Status yang boleh menjadi hasil sebuah pengembalian. */
    public const OUTCOMES = [
        AssetStatus::Available,
        AssetStatus::Maintenance,
        AssetStatus::Retired,
        AssetStatus::Lost,
    ];

    /**
     * Status yang disarankan dari kondisi barang saat kembali. Dipakai sebagai nilai
     * awal di formulir; petugas tetap boleh menentukan lain.
     */
    public static function suggestedOutcome(AssetCondition $condition): AssetStatus
    {
        return match ($condition) {
            AssetCondition::Damaged => AssetStatus::Maintenance,
            AssetCondition::Unusable => AssetStatus::Retired,
            default => AssetStatus::Available,
        };
    }

    /**
     * @param  array{condition_in: string, next_status?: string|null, returned_at?: string|null, return_notes?: string|null}  $data
     */
    public function handle(Asset $asset, array $data, User $actor): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $data, $actor): AssetAssignment {
            $locked = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            $assignment = $locked->assignments()->open()->latest('assigned_at')->first();

            if (! $assignment) {
                throw new AssetCustodyException(
                    "Aset {$locked->asset_code} sedang tidak dipegang siapa pun, jadi tidak ada yang bisa diterima kembali.",
                );
            }

            $condition = AssetCondition::from($data['condition_in']);
            $outcome = isset($data['next_status']) && $data['next_status']
                ? AssetStatus::from($data['next_status'])
                : self::suggestedOutcome($condition);

            if (! in_array($outcome, self::OUTCOMES, true)) {
                throw new AssetCustodyException('Status setelah pengembalian tidak sah.');
            }

            $returnedAt = isset($data['returned_at']) && $data['returned_at']
                ? Carbon::parse($data['returned_at'])
                : now();

            if ($returnedAt->lessThan($assignment->assigned_at)) {
                throw new AssetCustodyException('Tanggal pengembalian tidak boleh mendahului tanggal penyerahannya.');
            }

            $assignment->forceFill([
                'returned_at' => $returnedAt,
                'returned_to' => $actor->id,
                'condition_in' => $condition->value,
                'return_notes' => $data['return_notes'] ?? null,
            ])->save();

            $previousStatus = $locked->status;
            $employee = $assignment->employee;

            $locked->update([
                'status' => $outcome->value,
                'condition' => $condition->value,
            ]);

            $locked->transactions()->create([
                'assignment_id' => $assignment->id,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => AssetTransactionType::Returned->value,
                'from_status' => $previousStatus?->value,
                'to_status' => $outcome->value,
                'from_employee_id' => $employee?->id,
                'from_label' => $employee?->full_name,
                'condition' => $condition->value,
                'occurred_at' => $returnedAt,
                'notes' => $data['return_notes'] ?? null,
            ]);

            ActivityLogger::log(
                module: 'assets',
                event: 'returned',
                description: "Menerima kembali aset {$locked->asset_code} dari {$employee?->full_name} — kondisi {$condition->label()}, status menjadi {$outcome->label()}.",
                subject: $locked,
                properties: ['employee' => $employee?->full_name, 'condition' => $condition->value, 'status' => $outcome->value],
            );

            return $assignment;
        });
    }
}
