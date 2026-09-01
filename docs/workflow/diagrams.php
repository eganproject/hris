<?php

require_once __DIR__.'/svg-kit.php';

/**
 * Definisi seluruh diagram alur. Setiap fungsi mengembalikan satu objek Svg
 * selebar 510 pt — persis selebar bidang cetak A4 potret pada dokumen ini, jadi
 * gambarnya tidak pernah diperkecil dan ukuran hurufnya bisa dipercaya.
 */
const W = 510.0;

/** Deret kotak sejajar dengan jarak sama. */
function row(Svg $s, float $x, float $y, float $total, float $h, array $labels, string $kind, float $gap = 7, float $size = 7.5): array
{
    $n = count($labels);
    $w = ($total - $gap * ($n - 1)) / $n;
    $boxes = [];

    foreach (array_values($labels) as $i => $label) {
        $boxes[] = $s->box($x + $i * ($w + $gap), $y, $w, $h, $label, $kind, $size);
    }

    return $boxes;
}

/**
 * Satukan beberapa cabang pada satu rel mendatar, lalu lanjutkan dengan satu
 * panah menuju $xTarget. Ditarik satu per satu, panahnya akan menancap di luar
 * bentuk tujuan setiap kali lebar deret asal berbeda dari lebar tujuannya.
 */
function converge(Svg $s, array $xs, float $yFrom, float $yRail, float $xTarget, float $yTo): void
{
    foreach ($xs as $x) {
        $s->link([[$x, $yFrom], [$x, $yRail]]);
    }

    $s->link([[min($xs), $yRail], [max($xs), $yRail]]);
    $s->arrowDown($xTarget, $yRail, $yTo);
}

/** Kunci warna ringkas di kepala diagram. */
function legend(Svg $s, float $y, array $items, float $x = 0): void
{
    foreach ($items as $kind => $label) {
        [$fill, $stroke] = Svg::PALETTE[$kind];
        $s->raw(sprintf(
            '<rect x="%.2f" y="%.2f" width="16" height="9" rx="2" fill="%s" stroke="%s" stroke-width="0.8"/>',
            $x, $y, $fill, $stroke,
        ));
        $s->text($x + 20, $y + 7.6, $label, 7.5, '#475569', 'start');
        $x += 20 + Svg::textWidth($label, 7.5) + 14;
    }
}

/** Jalur pelaku (swimlane): kepala kolom + garis pemisah sepanjang diagram. */
function lanes(Svg $s, array $defs, float $top, float $bottom): void
{
    foreach ($defs as $i => $lane) {
        $s->laneHeader($lane['x'], $top, $lane['w'], $lane['title'], $lane['kind']);

        if ($i > 0) {
            $s->laneDivider($lane['x'] - 3, $top + 22, $bottom);
        }
    }
}

/* ====================================================================== 1 */

/** Peta besar: bagaimana kelima modul saling menyambung. */
function diagramPeta(): Svg
{
    $s = new Svg(W, 596);

    $bands = [
        ['y' => 0, 'h' => 74, 'title' => '1 · DATA INDUK — disiapkan HR sekali, dipakai seterusnya'],
        ['y' => 96, 'h' => 150, 'title' => '2 · JADWAL KERJA — menentukan hari kerja, shift, dan hari libur tiap karyawan'],
        ['y' => 268, 'h' => 78, 'title' => '3 · PENGAJUAN & PERSETUJUAN — mengubah apa yang seharusnya terjadi pada satu hari'],
        ['y' => 368, 'h' => 108, 'title' => '4 · ABSENSI HARIAN — menetapkan status tiap karyawan untuk tiap tanggal'],
        ['y' => 498, 'h' => 98, 'title' => '5 · LAPORAN — rekap untuk manajemen dan penggajian'],
    ];

    foreach ($bands as $band) {
        $s->band(0, $band['y'], W, $band['h'], $band['title']);
    }

    // Band 1 — data induk.
    row($s, 10, 26, W - 20, 36, [
        'Cabang & Divisi', 'Karyawan & Atasan', 'Shift', 'Pola Jadwal', 'Hari Libur', 'Jenis & Saldo Cuti', 'Mesin Absensi',
    ], 'data', 6, 7);

    // Band 2 — jadwal.
    $s->text(255, 134, 'Empat sumber baris roster', 7.5, '#64748b');
    row($s, 10, 140, W - 20, 46, [
        'Generator dari pola (otomatis)',
        'Override manual per hari (HR)',
        'Impor roster Excel (HR)',
        'Tukar jadwal yang disetujui',
    ], 'sys', 7, 7.5);
    $s->data(105, 202, 300, 36, 'employee_schedules — shift / libur / WFH per tanggal', 'data', 8);

    converge($s, [68.6, 192.9, 317.1, 441.4], 186, 194, 255, 202);

    // Band 3 — pengajuan.
    row($s, 10, 296, W - 20, 40, [
        'Cuti / Izin', 'Lembur', 'Koreksi Absensi', 'Tukar Jadwal',
    ], 'emp', 7, 8);

    // Band 4 — absensi.
    row($s, 10, 394, W - 20, 40, [
        'Punch mesin sidik jari', 'Absen mandiri WFH / Dinas Luar', 'Input manual HR',
    ], 'sys', 7, 7.5);
    $s->data(105, 448, 300, 22, 'attendances — status harian tiap karyawan', 'data', 8);
    converge($s, [89.3, 255, 420.7], 434, 441, 255, 448);

    // Band 5 — laporan.
    row($s, 10, 524, W - 20, 34, [
        'Rekap Kehadiran', 'Log Absensi Harian', 'Rekap Cuti', 'Rekap Lembur',
    ], 'ok', 7, 7.5);
    $s->box(140, 566, 230, 22, 'Tampilan layar · Excel · PDF · penggajian', 'plain', 7.5, 11);
    $s->arrowDown(255, 558, 566);

    // Sambungan antar tahap.
    $s->arrowDown(255, 74, 96);
    $s->arrowDown(255, 246, 268, null);
    $s->arrowDown(255, 346, 368);
    $s->arrowDown(255, 476, 498);

    // Tukar jadwal disetujui kembali menjadi baris roster.
    $s->elbow([[490, 296], [502, 296], [502, 224], [405, 224]], null, '#f97316');
    $s->tag(470, 258, 'tukar jadwal', 'middle', '#c2410c', 7);
    $s->tag(470, 268, 'jadi override', 'middle', '#c2410c', 7);

    // Cuti/koreksi yang disetujui mengubah status absensi.
    $s->elbow([[20, 336], [8, 336], [8, 459], [105, 459]], null, '#3b82f6');
    $s->tag(16, 360, 'cuti & koreksi yang disetujui mengubah status harian', 'start', '#1d4ed8', 6.5);

    return $s;
}

/* ====================================================================== 2 */

function diagramJadwal(): Svg
{
    $s = new Svg(W, 572);

    legend($s, 2, ['hr' => 'Tindakan HR', 'sys' => 'Otomatis oleh sistem', 'dec' => 'Keputusan', 'data' => 'Data tersimpan']);

    $s->pill(140, 24, 230, 24, 'MULAI — data induk & karyawan siap', 'start', 8.5);
    $s->arrowDown(255, 48, 62);

    $s->box(130, 62, 250, 40, 'HR menyiapkan Shift (jam masuk–pulang, toleransi terlambat, menit istirahat) dan Pola Jadwal', 'hr', 8);
    $s->arrowDown(255, 102, 116);

    $s->diamond(255, 144, 210, 56, 'Karyawan mengikuti jam kantor tetap?');
    $s->arrowRight(360, 400, 144, 'Ya');
    $s->box(400, 116, 110, 56, 'Jadwal diturunkan dari pola saat dibaca — tanpa baris roster', 'sys', 7);

    $s->arrowDown(255, 172, 190, 'Tidak');
    $s->box(115, 190, 280, 40, 'HR menugaskan pola ke karyawan: tanggal mulai, opsional tanggal selesai. Bisa massal dari daftar Belum Terjadwal', 'hr', 7.5);
    $s->arrowDown(255, 230, 250);

    $s->text(255, 258, 'Empat sumber baris roster — semuanya menulis ke tabel yang sama', 7.5, '#64748b');
    row($s, 0, 266, W, 54, [
        'Generator memateraikan pola ke depan (bawaan 90 hari)',
        'Override manual satu hari oleh HR',
        'Impor roster bulanan dari Excel',
        'Tukar jadwal yang sudah disetujui',
    ], 'sys', 7, 7.5);

    converge($s, [61.1, 190.4, 319.6, 448.9], 320, 329, 255, 338);

    $s->data(90, 338, 330, 40, 'employee_schedules — untuk tiap tanggal: shift, libur, atau WFH', 'data', 8.5);
    $s->arrowDown(255, 378, 396);

    $s->box(105, 396, 300, 42, 'Setiap perubahan roster memicu penghitungan ulang absensi yang sudah tercatat pada rentang itu', 'sys', 7.5);
    $s->arrowDown(255, 438, 460);

    $left = $s->box(60, 460, 180, 38, 'Karyawan melihat menu Jadwal Saya', 'emp', 8);
    $right = $s->box(270, 460, 180, 38, 'Dipakai sebagai dasar Absensi Harian', 'ok', 8);
    $s->elbow([[255, 452], [150, 452], [150, 460]]);
    $s->elbow([[255, 452], [360, 452], [360, 460]]);
    $s->raw('<line x1="255" y1="438" x2="255" y2="452" stroke="'.Svg::LINE.'" stroke-width="1"/>');

    // Jalur jam kantor berhenti di terminatornya sendiri. Menariknya turun sampai
    // ke kotak keluaran akan memotong deretan sumber roster yang selebar halaman.
    $s->arrowDown(455, 172, 188);
    $s->box(400, 188, 110, 44, 'Langsung dipakai sebagai dasar Absensi Harian', 'ok', 7);

    $h = $s->note(0, 512, 250, 'Baris manual tidak pernah ditimpa generator. Bila dua penugasan pola beririsan, yang berlaku adalah yang paling baru ditugaskan — bukan yang tanggal mulainya paling akhir.');
    $s->note(260, 512, 250, 'Tugas terjadwal 00.20 menjalankan schedule:generate-roster tiap hari, sehingga cakupan roster selalu maju ke depan tanpa perlu digenerate manual tiap bulan.');

    return $s;
}

/* ====================================================================== 3 */

function diagramTukarJadwal(): Svg
{
    $s = new Svg(W, 432);

    $laneDefs = [
        ['x' => 0, 'w' => 156, 'title' => 'Karyawan Pengaju', 'kind' => 'emp'],
        ['x' => 162, 'w' => 156, 'title' => 'Rekan yang Diminta', 'kind' => 'sup'],
        ['x' => 324, 'w' => 186, 'title' => 'HR & Sistem', 'kind' => 'hr'],
    ];
    lanes($s, $laneDefs, 0, 428);

    $s->box(3, 30, 150, 52, 'Ajukan tukar shift, minta digantikan, atau tukar hari libur — sertakan bukti bila perlu', 'emp', 7.5);
    $s->arrowDown(78, 82, 96);
    $s->box(3, 96, 150, 38, 'Pemeriksaan bentrok jadwal kedua pihak', 'sys', 7.5);
    $s->arrowRight(153, 165, 115, null);
    $s->box(165, 96, 150, 38, 'Status: Menunggu Rekan — notifikasi terkirim', 'sup', 7.5);
    $s->arrowDown(240, 134, 150);

    $s->diamond(240, 178, 140, 54, 'Rekan setuju?');
    $s->elbow([[170, 178], [78, 178], [78, 196]], 'Tidak');
    $s->box(3, 196, 150, 38, 'Status Ditolak — pengaju diberi tahu', 'no', 7.5);

    $s->elbow([[240, 205], [240, 226], [330, 226]], 'Ya');
    $s->box(330, 208, 176, 38, 'Status: Menunggu HR', 'hr', 8);
    $s->arrowDown(418, 246, 262);
    $s->diamond(418, 290, 160, 54, 'HR setuju? (bentrok diperiksa ulang)');
    // Jalur tolak HR dibawa memutar di bawah jalur setuju, lalu masuk dari bawah
    // kotak "Ditolak" — kalau disejajarkan, dua garis mendatarnya nyaris berimpit.
    $s->elbow([[338, 290], [326, 290], [326, 258], [78, 258], [78, 234]], 'Tidak');

    $s->arrowDown(418, 317, 336, 'Ya');
    $s->box(330, 336, 176, 44, 'Hasilnya ditulis sebagai override manual pada roster kedua karyawan', 'sys', 7.5);
    $s->arrowDown(418, 380, 396);
    $s->box(330, 396, 176, 26, 'Absensi hari itu dihitung ulang', 'ok', 7.5);

    $s->note(3, 276, 310, 'Bentrok diperiksa dua kali — saat diajukan dan sekali lagi saat HR memutuskan — karena roster bisa berubah di antaranya. Pengaju dapat membatalkan selama belum ada keputusan HR.');
    $s->note(3, 344, 310, 'Tiga bentuk pengajuan: tukar shift dengan rekan, minta digantikan, dan tukar hari libur. Semuanya berakhir sebagai override manual sehingga tidak akan tertimpa generator roster.');

    return $s;
}

/* ====================================================================== 4 */

function diagramPunch(): Svg
{
    $s = new Svg(W, 464);

    legend($s, 2, ['emp' => 'Karyawan', 'sys' => 'Otomatis', 'hr' => 'HR', 'dec' => 'Keputusan']);

    $s->box(165, 22, 190, 32, 'Karyawan menempelkan sidik jari di mesin', 'emp', 8);
    $s->arrowDown(260, 54, 68);
    $s->box(165, 68, 190, 42, 'Mesin ZKTeco mengirim data ATTLOG ke alamat /iclock/cdata', 'sys', 8);
    $s->arrowDown(260, 110, 124);
    $s->box(165, 124, 190, 48, 'Serial mesin diperiksa terhadap daftar yang diizinkan; punch kembar disaring lewat sidik unik', 'sys', 7.5);
    $s->arrowDown(260, 172, 186);

    $s->diamond(260, 214, 190, 56, 'PIN mesin sudah terpetakan ke karyawan?');

    $s->elbow([[165, 214], [75, 214], [75, 238]], 'Tidak');
    $s->box(0, 238, 150, 44, 'Punch masuk daftar Belum Terpetakan di menu Data Punch', 'no', 7.5);
    $s->arrowDown(75, 282, 294);
    $s->box(0, 294, 150, 52, 'HR memetakan PIN ke karyawan — punch lama dengan PIN itu ikut ditarik ulang', 'hr', 7.5);
    $s->elbow([[150, 320], [245, 320], [245, 360]]);

    $s->arrowDown(260, 242, 360, 'Ya');
    $s->box(165, 360, 190, 56, 'Punch pertama dan terakhir dalam jendela shift menjadi jam masuk dan jam pulang', 'sys', 7.5);
    $s->arrowDown(260, 416, 432);
    $s->pill(150, 432, 220, 26, 'Lanjut ke penghitungan status →', 'ok', 8);

    $s->note(370, 22, 140, 'Jendela kepemilikan punch: 3 jam sebelum shift mulai sampai 10 jam sesudah shift selesai, dan selalu dipotong sebelum shift berikutnya — satu punch hanya dimiliki oleh satu hari kerja.');
    $s->note(370, 150, 140, 'Karena kiriman kembar disaring, mesin boleh mengirim ulang data yang sama tanpa menggandakan absensi.');
    $s->note(370, 248, 140, 'Shift malam tetap benar: punch pulang setelah tengah malam masih dimiliki hari sebelumnya.');

    return $s;
}

/* ====================================================================== 5 */

function diagramResolver(): Svg
{
    $s = new Svg(W, 556);

    legend($s, 2, ['emp' => 'Sumber jam', 'dec' => 'Pemeriksaan berurutan', 'ok' => 'Status yang ditetapkan', 'data' => 'Data tersimpan']);

    row($s, 0, 20, W, 40, [
        'Jam dari mesin sidik jari',
        'Absen mandiri WFH / Dinas Luar: selfie + titik lokasi',
        'Jam diisi manual oleh HR di papan Absensi Harian',
    ], 'emp', 8, 7.5);

    converge($s, [85, 255, 425], 60, 70, 255, 80);

    $s->box(105, 80, 300, 26, 'Penghitungan status hari itu dijalankan', 'sys', 8.5);

    $rows = [
        [144, '1. Hari libur nasional di cabangnya?', 'Libur Nasional — seluruh jam kerja hari itu dihitung sebagai lembur', 'ok'],
        [214, '2. Ada cuti / izin yang sudah disetujui?', 'Jenis WFH atau Dinas Luar: label kerja jarak jauh dipakai, perhitungan shift tetap berjalan. Jenis lain (Cuti, Izin, Sakit): status itu dipakai, nol jam kerja', 'ok'],
        [284, '3. Ada shift terjadwal hari itu?', 'Tidak ada shift: bila ada punch → Hadir dan seluruh jamnya lembur; bila tidak ada punch → Libur sesuai jadwal', 'ok'],
        [354, '4. Ada jam masuk yang tercatat?', 'Tidak ada: Alfa. Kecuali hari kerja jarak jauh — statusnya tetap WFH / Dinas Luar dengan nol jam', 'no'],
    ];

    // Dua pemeriksaan pertama keluar lewat "Ya"; dua berikutnya lewat "Tidak" —
    // label pada panah lanjutan ke bawah karena itu ikut bertukar.
    $prev = 106;

    foreach ($rows as $i => [$y, $question, $answer, $kind]) {
        $cy = $y + 28;
        $branchIsYes = $i < 2;
        // Pemeriksaan 1–3 dilanjutkan bila jawabannya "tidak"; pemeriksaan 3 dan 4
        // sebaliknya, sehingga panah masuk ke pemeriksaan 4 berlabel "Ya".
        $s->arrowDown(165, $prev, $y, [null, 'Tidak', 'Tidak', 'Ya'][$i]);
        $s->diamond(165, $cy, 200, 56, $question);
        $s->arrowRight(265, 300, $cy, $branchIsYes ? 'Ya' : 'Tidak');
        $s->box(300, $y, 210, 56, $answer, $kind, 7.5);
        $prev = $y + 56;
    }

    $s->arrowDown(165, 410, 428, 'Ya');
    $s->box(60, 428, 210, 62, 'Hitung menit terlambat, pulang cepat, jam kerja (dikurangi istirahat) dan menit lembur dari jendela shift', 'sys', 7.5);
    $s->arrowRight(270, 300, 459);
    $s->box(300, 428, 210, 62, 'Bandingkan dengan toleransi shift → Terlambat, Pulang Cepat, atau Hadir. Label WFH / Dinas Luar tetap dipertahankan', 'ok', 7.5);

    $s->link([[405, 490], [405, 504], [165, 504], [165, 490]]);
    $s->arrowDown(255, 504, 518);
    $s->data(105, 518, 300, 34, 'attendances — status + menit terlambat, kerja, dan lembur', 'data', 8);


    return $s;
}

/* ====================================================================== 6 */

function diagramKoreksi(): Svg
{
    $s = new Svg(W, 240);

    $laneDefs = [
        ['x' => 0, 'w' => 168, 'title' => 'Karyawan', 'kind' => 'emp'],
        ['x' => 174, 'w' => 168, 'title' => 'HR / Atasan Berwenang', 'kind' => 'hr'],
        ['x' => 348, 'w' => 162, 'title' => 'Sistem', 'kind' => 'sys'],
    ];
    lanes($s, $laneDefs, 0, 234);

    $s->box(6, 30, 156, 52, 'Dari menu Absensi Saya: ajukan koreksi — tanggal, usulan jam masuk / pulang, alasan', 'emp', 7.5);
    $s->arrowRight(162, 180, 56);
    $s->box(180, 30, 156, 52, 'Koreksi masuk daftar Menunggu, tersaring menurut cakupan data petugas', 'hr', 7.5);
    $s->arrowDown(258, 82, 100);

    $s->diamond(258, 128, 156, 56, 'Koreksi disetujui?');
    $s->elbow([[180, 128], [84, 128], [84, 152]], 'Tidak');
    $s->box(6, 152, 156, 44, 'Status Ditolak beserta catatan alasan', 'no', 7.5);

    $s->arrowRight(336, 356, 128, 'Ya');
    $s->box(356, 100, 150, 56, 'Satu transaksi: jam absensi diperbarui lalu status koreksi disetujui', 'sys', 7.5);
    $s->arrowDown(431, 156, 176);
    $s->box(356, 176, 150, 44, 'Status hari itu dihitung ulang dari jam yang baru', 'ok', 7.5);


    return $s;
}

/* ====================================================================== 7 */

function diagramCuti(): Svg
{
    $s = new Svg(W, 604);

    $laneDefs = [
        ['x' => 0, 'w' => 198, 'title' => 'Karyawan', 'kind' => 'emp'],
        ['x' => 204, 'w' => 150, 'title' => 'Atasan Langsung', 'kind' => 'sup'],
        ['x' => 360, 'w' => 150, 'title' => 'HR & Sistem', 'kind' => 'hr'],
    ];
    lanes($s, $laneDefs, 0, 598);

    $s->box(365, 30, 140, 56, 'HR menyiapkan Jenis Cuti (potong saldo? status absensi apa?) dan Saldo tahunan', 'hr', 7.5);
    $s->arrowRight(365, 184, 58, 'jenis & saldo tersedia');

    $s->box(22, 30, 158, 56, 'Karyawan mengisi pengajuan: jenis cuti, tanggal mulai–selesai, alasan, lampiran', 'emp', 7.5);
    $s->arrowDown(101, 86, 102);

    $s->diamond(101, 130, 180, 56, 'Lolos pemeriksaan tumpang tindih & sisa saldo?');
    $s->elbow([[11, 130], [6, 130], [6, 58], [22, 58]], 'Tidak');

    $s->arrowDown(101, 158, 176, 'Ya');
    $s->diamond(101, 204, 180, 56, 'Punya atasan langsung?');

    $s->arrowRight(191, 209, 204, 'Ya');
    $s->box(209, 178, 140, 52, 'Status Menunggu Atasan — notifikasi masuk', 'sup', 7.5);

    $s->elbow([[101, 232], [101, 262], [365, 262]], 'Tidak');
    $s->box(365, 240, 140, 52, 'Status Menunggu HR — jalan keluar bila belum punya atasan', 'hr', 7.5);

    $s->arrowDown(279, 230, 302);
    $s->diamond(279, 330, 140, 56, 'Disetujui?');
    $s->elbow([[435, 292], [435, 330], [349, 330]]);

    $s->elbow([[209, 330], [190, 330], [190, 362], [180, 362]], 'Tidak');
    $s->box(22, 340, 158, 44, 'Status Ditolak beserta catatan; karyawan diberi tahu', 'no', 7.5);

    $s->elbow([[279, 358], [279, 388], [365, 388]], 'Ya');
    $s->box(365, 366, 140, 56, 'Satu transaksi: status Disetujui dan absensi tiap harinya dihitung ulang', 'sys', 7.5);

    $s->elbow([[435, 422], [435, 442], [255, 442], [255, 452]]);
    $s->data(105, 452, 300, 34, 'attendances — hari cuti berubah menjadi Cuti, Sakit, WFH, atau Dinas Luar', 'data', 7.5);

    $s->arrowDown(255, 486, 500);
    $s->box(105, 500, 300, 26, 'Ikut terhitung di Rekap Kehadiran dan Rekap Cuti', 'ok', 8);

    $s->note(0, 540, 250, 'Persetujuan satu tahap: keputusan atasan bersifat final. HR memutuskan hanya sebagai jalan keluar — bagi karyawan yang belum punya atasan.');
    $s->note(260, 540, 250, 'Pengajuan yang menunggu maupun yang disetujui sama-sama menahan kuota, jadi saldo tidak bisa dipesan dua kali. Karyawan boleh membatalkan selama masih menunggu.');

    return $s;
}

/* ====================================================================== 8 */

function diagramLembur(): Svg
{
    $s = new Svg(W, 556);

    $laneDefs = [
        ['x' => 0, 'w' => 186, 'title' => 'Karyawan', 'kind' => 'emp'],
        ['x' => 192, 'w' => 162, 'title' => 'Atasan Langsung', 'kind' => 'sup'],
        ['x' => 360, 'w' => 150, 'title' => 'HR & Sistem', 'kind' => 'hr'],
    ];
    lanes($s, $laneDefs, 0, 550);

    $s->diamond(93, 58, 176, 52, 'Atasan langsung sudah diatur?');
    $s->arrowRight(181, 365, 58, 'Belum');
    $s->box(365, 32, 140, 52, 'Karyawan diminta menghubungi HR — pengajuan belum bisa dibuat', 'no', 7.5);

    $s->arrowDown(93, 84, 100, 'Sudah');
    $s->box(15, 100, 156, 56, 'Isi pengajuan: tanggal (paling lambat hari ini), jam mulai–selesai, uraian pekerjaan', 'emp', 7.5);
    $s->arrowDown(93, 156, 172);

    $s->diamond(100, 202, 180, 68, 'Durasi wajar dan belum ada pengajuan lain di tanggal itu?');
    $s->elbow([[10, 202], [6, 202], [6, 128], [15, 128]], 'Tidak');

    $s->arrowDown(100, 236, 252, 'Ya');
    $s->box(15, 252, 156, 44, 'Status Menunggu — notifikasi ke atasan', 'emp', 7.5);
    $s->box(360, 240, 150, 56, 'Menit lembur hasil hitung absensi hari itu disimpan sebagai pembanding', 'sys', 7.5);
    $s->arrowRight(171, 360, 264, 'dicatat sistem', Svg::LINE, true);

    $s->elbow([[93, 296], [93, 318], [273, 318], [273, 322]]);
    $s->diamond(273, 352, 170, 60, 'Disetujui? Menit boleh disesuaikan');

    $s->elbow([[188, 352], [180, 352], [180, 392], [171, 392]], 'Tidak');
    $s->box(15, 370, 156, 44, 'Status Ditolak beserta catatan', 'no', 7.5);

    $s->elbow([[273, 382], [273, 412], [360, 412]], 'Ya');
    $s->box(360, 390, 150, 44, 'Menit yang disetujui dicatat', 'ok', 7.5);
    $s->arrowDown(435, 434, 452);
    $s->box(300, 452, 210, 40, 'Masuk Rekap Lembur bulanan HR — dasar perhitungan penggajian', 'ok', 7.5);

    $s->note(0, 508, 290, 'Pemisahan wewenang: tidak ada yang boleh memutuskan pengajuan lemburnya sendiri. Karyawan dapat membatalkan selama masih menunggu, dan atasannya diberi tahu.');
    $s->note(300, 508, 210, 'Batas kewajaran: durasi harus lebih dari 0 menit dan tidak lebih dari 12 jam.');

    return $s;
}

/* ====================================================================== 9 */

function diagramLaporan(): Svg
{
    $s = new Svg(W, 506);

    legend($s, 2, ['data' => 'Sumber data', 'sys' => 'Penyaringan', 'ok' => 'Laporan', 'hr' => 'Keluaran']);

    row($s, 0, 22, W, 40, [
        'attendances — status harian',
        'leave_requests — cuti disetujui',
        'overtime_approvals — lembur disetujui',
    ], 'data', 8, 7.5);

    converge($s, [85, 255, 425], 62, 74, 255, 86);

    $s->box(75, 86, 360, 46, 'Disaring menurut cakupan data pemakai (cabang, divisi, atau hanya bawahannya) lalu menurut periode dan filter di layar', 'sys', 7.5);
    $s->arrowDown(255, 132, 152);

    $reports = [
        ['Rekap Kehadiran (bulanan)', 'Per karyawan: total hari, hadir, terlambat, pulang cepat, alfa, cuti, sakit, menit terlambat, menit kerja, menit lembur'],
        ['Log Absensi Harian (bulanan)', 'Satu baris per karyawan per tanggal: jam masuk, jam pulang, status, plus ringkasan seluruh periode'],
        ['Rekap Cuti (tahunan)', 'Per jenis cuti: kuota, sudah terpakai, dan sisa saldo tiap karyawan'],
        ['Rekap Lembur (bulanan)', 'Jumlah hari lembur dan total menit yang disetujui atasan, diurutkan dari yang terbesar'],
    ];

    $x = 0;
    $w = (W - 3 * 8) / 4;

    foreach ($reports as $i => [$title, $detail]) {
        $bx = $x + $i * ($w + 8);
        $s->box($bx, 152, $w, 34, $title, 'ok', 7.5);
        $s->box($bx, 190, $w, 78, $detail, 'plain', 7);
        $s->arrowDown($bx + $w / 2, 186, 190);
        $s->elbow([[255, 144], [$bx + $w / 2, 144], [$bx + $w / 2, 152]]);
    }

    // Empat laporan bertemu di satu rel mendatar sebelum bercabang ke tiga bentuk
    // keluaran; ditarik satu-satu, garisnya tidak akan pernah sejajar rapi.
    foreach ([0, 1, 2, 3] as $i) {
        $s->link([[$i * ($w + 8) + $w / 2, 268], [$i * ($w + 8) + $w / 2, 282]]);
    }
    $s->link([[$w / 2, 282], [3 * ($w + 8) + $w / 2, 282]]);

    foreach ([85, 255, 425] as $x) {
        $s->arrowDown($x, 282, 296);
    }

    row($s, 0, 296, W, 32, ['Tampilan layar (bisa disaring)', 'Unduh Excel', 'Unduh PDF'], 'hr', 8, 8);
    $s->link([[85, 328], [85, 342], [425, 342], [425, 328]]);
    $s->arrowDown(255, 342, 356);
    $s->box(105, 356, 300, 30, 'Detail per karyawan · bahan penggajian · arsip bulanan', 'plain', 8);

    $s->note(0, 400, 250, 'Angka “hadir” memakai satu definisi bersama: Hadir, Terlambat, Pulang Cepat, WFH, dan Dinas Luar semuanya terhitung bekerja — sehingga rekap, papan harian, dan ekspor tidak pernah berbeda.');
    $s->note(260, 400, 250, 'Kolom “total hari” hanya menghitung hari kerja terjadwal; libur nasional dan libur jadwal tidak ikut, agar rincian hadir + alfa + cuti + sakit berdamai dengan totalnya.');

    $s->note(0, 470, W, 'Hak akses melihat dan mengekspor tiap laporan diatur terpisah, jadi seseorang bisa diberi hak membaca rekap tanpa diberi hak mengunduhnya.', 7);

    return $s;
}

/**
 * @return array<string, Svg>
 */
function allDiagrams(): array
{
    return [
        'peta' => diagramPeta(),
        'jadwal' => diagramJadwal(),
        'tukar-jadwal' => diagramTukarJadwal(),
        'punch' => diagramPunch(),
        'resolver' => diagramResolver(),
        'koreksi' => diagramKoreksi(),
        'cuti' => diagramCuti(),
        'lembur' => diagramLembur(),
        'laporan' => diagramLaporan(),
    ];
}
