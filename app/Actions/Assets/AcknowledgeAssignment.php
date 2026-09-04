<?php

namespace App\Actions\Assets;

use App\Enums\AssetTransactionType;
use App\Exceptions\AssetCustodyException;
use App\Models\AssetAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ApprovalNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Pengakuan karyawan bahwa ia memang menerima aset yang diserahkan kepadanya.
 *
 * Wajib untuk semua kategori. Sebuah serah-terima yang hanya dicatat sepihak oleh
 * petugas tidak punya nilai saat barangnya hilang setahun kemudian — yang dipegang
 * perusahaan cuma catatannya sendiri. Pengakuan ini yang membuat catatannya berdiri
 * di atas dua pihak.
 */
class AcknowledgeAssignment
{
    public function __construct(private readonly ApprovalNotifier $notifier) {}

    public function handle(AssetAssignment $assignment, User $actor, ?string $note = null): AssetAssignment
    {
        $assignment = DB::transaction(function () use ($assignment, $actor, $note): AssetAssignment {
            $locked = AssetAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if (! $locked->isOpen()) {
                throw new AssetCustodyException('Serah-terima ini sudah ditutup, jadi tidak perlu dikonfirmasi lagi.');
            }

            if ($locked->isAcknowledged()) {
                throw new AssetCustodyException('Serah-terima ini sudah Anda konfirmasi sebelumnya.');
            }

            $locked->forceFill([
                'acknowledged_at' => now(),
                'acknowledgement_note' => $note,
            ])->save();

            $asset = $locked->asset;

            $asset->transactions()->create([
                'assignment_id' => $locked->id,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => AssetTransactionType::Acknowledged->value,
                'from_status' => $asset->status?->value,
                'to_status' => $asset->status?->value,
                'to_employee_id' => $locked->employee_id,
                'to_label' => $locked->employee?->full_name,
                'occurred_at' => now(),
                'notes' => $note,
            ]);

            ActivityLogger::log(
                module: 'assets',
                event: 'acknowledged',
                description: "{$locked->employee?->full_name} mengonfirmasi penerimaan aset {$asset->asset_code}.",
                subject: $asset,
            );

            return $locked;
        });

        $this->notifier->assetAcknowledged($assignment);

        return $assignment;
    }
}
