<?php

namespace App\Console\Commands;

use App\Models\DeviceCommunication;
use Illuminate\Console\Command;

/**
 * Pemangkasan log komunikasi mesin absensi, dua tahap.
 *
 * Isinya dibuang jauh lebih cepat daripada barisnya. Sebuah mesin yang sehat
 * menyapa server sepanjang hari, dan isi kirimannya hanya berguna selama beberapa
 * hari pertama — ketika seseorang masih menelusuri kejadian yang baru lewat.
 * Sesudah itu yang masih bernilai tinggal ringkasannya: kapan mesinnya mengirim,
 * berapa record, dari IP mana. Ringkasan itu jauh lebih ringan, jadi ia boleh
 * bertahan lebih lama.
 *
 * Menyamakan keduanya berarti memilih salah satu kerugian: kehilangan jejak
 * kehadiran mesin terlalu cepat, atau menyimpan puluhan ribu isi kiriman yang sudah
 * tidak dibaca siapa pun.
 */
class PruneDeviceCommunications extends Command
{
    /** Berapa lama baris ringkasannya disimpan. */
    private const RETENTION_DAYS = 14;

    /** Berapa lama isi kirimannya disimpan. */
    private const PAYLOAD_RETENTION_DAYS = 3;

    protected $signature = 'devices:prune-communications';

    protected $description = 'Pangkas log komunikasi mesin absensi: isi kiriman lama dibuang, ringkasannya menyusul kemudian';

    public function handle(): int
    {
        $payloads = DeviceCommunication::query()
            ->whereNotNull('payload')
            ->where('created_at', '<', now()->subDays(self::PAYLOAD_RETENTION_DAYS))
            ->update(['payload' => null]);

        $rows = DeviceCommunication::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $this->info("Isi kiriman dibuang: {$payloads}. Baris log dihapus: {$rows}.");

        return self::SUCCESS;
    }
}
