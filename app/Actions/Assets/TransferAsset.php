<?php

namespace App\Actions\Assets;

use App\Enums\AssetTransactionType;
use App\Exceptions\AssetCustodyException;
use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\Branch;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan aset ke lokasi kerja lain (dan, bila perlu, ke divisi lain).
 *
 * Yang berpindah adalah TEMPAT barangnya berada, bukan kepemilikannya: owning_branch
 * tetap, sehingga kode aset dan pembukuan cabang pemiliknya tidak ikut bergeser saat
 * sebuah monitor dititipkan ke cabang sebelah.
 *
 * Perpindahan antar-KARYAWAN sengaja tidak lewat sini, melainkan lewat Pengembalian
 * lalu Penyerahan. Terdengar lebih panjang, tapi itu memang dua kejadian: barangnya
 * diperiksa saat kembali dan diperiksa lagi saat diserahkan, dan keduanya perlu
 * tercatat kondisinya. Satu tombol "oper ke orang lain" akan melewati pemeriksaan
 * itu, dan kerusakan yang terjadi di tangan siapa jadi tidak bisa ditelusuri.
 */
class TransferAsset
{
    /**
     * @param  array{current_branch_id: int, department_id?: int|null, notes?: string|null}  $data
     */
    public function handle(Asset $asset, array $data, User $actor): AssetTransaction
    {
        return DB::transaction(function () use ($asset, $data, $actor): AssetTransaction {
            $locked = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            if ($locked->assignments()->open()->exists()) {
                throw new AssetCustodyException(
                    "Aset {$locked->asset_code} sedang dipegang karyawan. Terima kembali dulu sebelum memindahkannya ke lokasi lain.",
                );
            }

            if ($locked->status?->isClosed()) {
                throw new AssetCustodyException("Aset {$locked->asset_code} sudah dilepas dan hanya bisa dibaca.");
            }

            $targetBranchId = (int) $data['current_branch_id'];

            if ($targetBranchId === (int) $locked->current_branch_id) {
                throw new AssetCustodyException('Aset sudah berada di lokasi itu.');
            }

            $from = $locked->currentBranch;
            $to = Branch::query()->findOrFail($targetBranchId);

            $locked->update(array_filter([
                'current_branch_id' => $targetBranchId,
                'department_id' => $data['department_id'] ?? null,
            ], fn ($value) => $value !== null));

            if (array_key_exists('department_id', $data) && $data['department_id']) {
                $locked->departments()->syncWithoutDetaching([$data['department_id']]);
            }

            $transaction = $locked->transactions()->create([
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => AssetTransactionType::Transferred->value,
                'from_status' => $locked->status?->value,
                'to_status' => $locked->status?->value,
                'from_branch_id' => $from?->id,
                'to_branch_id' => $to->id,
                'from_label' => $from?->name,
                'to_label' => $to->name,
                'condition' => $locked->condition?->value,
                'occurred_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            ActivityLogger::log(
                module: 'assets',
                event: 'transferred',
                description: "Memindahkan aset {$locked->asset_code} dari {$from?->name} ke {$to->name}.",
                subject: $locked,
                properties: ['from' => $from?->name, 'to' => $to->name],
            );

            return $transaction;
        });
    }
}
