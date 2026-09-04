<?php

namespace App\Console\Commands;

use App\Models\AssetAssignment;
use App\Support\ApprovalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pengingat harian untuk serah-terima aset.
 *
 * Dua hal yang menggantung diam-diam kalau tidak ada yang mengingatkan: serah-terima
 * yang belum diakui karyawannya, dan aset yang belum kembali sampai tenggatnya.
 *
 * Tiap serah-terima diingatkan paling banyak SEKALI PER HARI, ditandai lewat kolom
 * *_reminded_at. Tanpa penanda itu, pengingat yang sama terkirim tiap pagi sampai
 * lonceng notifikasinya berhenti dibaca — dan justru pengingat yang penting ikut
 * tenggelam.
 */
class NotifyAssetCustody extends Command
{
    /** Berapa hari serah-terima dibiarkan sebelum karyawannya ditagih konfirmasi. */
    private const ACKNOWLEDGEMENT_GRACE_DAYS = 3;

    /** Sisa hari menjelang tenggat yang layak diingatkan. */
    private const REMIND_DAYS_BEFORE = [7, 1, 0];

    /** Setelah lewat tenggat, diingatkan berkala tiap sekian hari. */
    private const OVERDUE_EVERY_DAYS = 7;

    protected $signature = 'assets:notify-custody';

    protected $description = 'Ingatkan konfirmasi serah-terima aset dan pengembalian yang mendekat atau telat';

    public function handle(ApprovalNotifier $notifier): int
    {
        $acknowledgement = $this->remindAcknowledgements($notifier);
        $returns = $this->remindReturns($notifier);

        $this->info("Pengingat konfirmasi: {$acknowledgement}. Pengingat pengembalian: {$returns}.");

        return self::SUCCESS;
    }

    private function remindAcknowledgements(ApprovalNotifier $notifier): int
    {
        $sent = 0;

        AssetAssignment::query()
            ->awaitingAcknowledgement()
            ->where('assigned_at', '<=', now()->subDays(self::ACKNOWLEDGEMENT_GRACE_DAYS))
            ->where(function ($query) {
                $query->whereNull('acknowledgement_reminded_at')
                    ->orWhereDate('acknowledgement_reminded_at', '<', today());
            })
            ->with('asset', 'employee.user')
            ->chunkById(100, function ($assignments) use ($notifier, &$sent): void {
                foreach ($assignments as $assignment) {
                    $notifier->assetAcknowledgementReminder($assignment);
                    $assignment->forceFill(['acknowledgement_reminded_at' => now()])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    private function remindReturns(ApprovalNotifier $notifier): int
    {
        $sent = 0;

        AssetAssignment::query()
            ->open()
            ->whereNotNull('expected_return_at')
            ->where(function ($query) {
                $query->whereNull('return_reminded_at')
                    ->orWhereDate('return_reminded_at', '<', today());
            })
            ->with('asset', 'employee.user')
            ->chunkById(100, function ($assignments) use ($notifier, &$sent): void {
                foreach ($assignments as $assignment) {
                    $daysLeft = $this->daysLeft($assignment);

                    if (! $this->shouldRemind($daysLeft)) {
                        continue;
                    }

                    $notifier->assetReturnReminder($assignment, $daysLeft);
                    $assignment->forceFill(['return_reminded_at' => now()])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    /** Positif = masih ada sisa waktu, 0 = jatuh tempo hari ini, negatif = sudah telat. */
    private function daysLeft(AssetAssignment $assignment): int
    {
        return (int) Carbon::parse($assignment->expected_return_at)->startOfDay()
            ->diffInDays(today(), false) * -1;
    }

    private function shouldRemind(int $daysLeft): bool
    {
        if ($daysLeft >= 0) {
            return in_array($daysLeft, self::REMIND_DAYS_BEFORE, true);
        }

        // Sudah telat: ditagih berkala, bukan tiap hari.
        return abs($daysLeft) % self::OVERDUE_EVERY_DAYS === 0;
    }
}
