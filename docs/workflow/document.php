<?php

/**
 * Isi dokumen "Alur Kerja HRIS": sampul, daftar isi, sembilan bagian bergambar,
 * dan dua lampiran acuan. Diagram dirujuk lewat berkas SVG yang sudah ditulis
 * lebih dulu oleh render.php.
 *
 * Pemenggalan halaman diatur tangan, satu <div class="page"> per halaman.
 * Dompdf tidak bisa memotong sebuah gambar, jadi diagram yang tinggi selalu
 * pindah utuh ke halaman berikutnya bila sisa ruangnya kurang — halaman yang
 * dibiarkan mengalir sendiri berakhir setengah kosong berselang-seling.
 */

/**
 * Urutan halaman dokumen. Karena pemenggalan halaman diatur tangan — satu
 * <div class="page"> per lembar, dan tak satu pun isinya melimpah — nomor
 * halaman sama dengan urutan di daftar ini, sehingga daftar isi bisa menyebut
 * nomornya tanpa menunggu PDF-nya jadi. render-workflow.php memeriksa jumlah
 * halaman hasil cetak terhadap daftar ini dan mengeluh bila tidak cocok.
 */
const PAGES = [
    'sampul', 'isi', 'panduan', 'peta',
    'jadwal', 'jadwal-lanjutan', 'tukar', 'punch', 'resolver', 'koreksi',
    'cuti', 'cuti-lanjutan', 'lembur', 'lembur-lanjutan',
    'laporan', 'laporan-lanjutan', 'lampiran-a', 'lampiran-b',
];

function pageOf(string $key): int
{
    $index = array_search($key, PAGES, true);

    if ($index === false) {
        throw new InvalidArgumentException("Halaman tidak dikenal: {$key}");
    }

    return $index + 1;
}

/** Daftar bertanda titik. */
function ul(array $items): string
{
    return '<ul>'.implode('', array_map(fn ($i) => '<li>'.$i.'</li>', $items)).'</ul>';
}

/** Tabel sederhana: baris pertama menjadi kepala tabel. */
function table(array $head, array $rows, array $widths = []): string
{
    $html = '<table><thead><tr>';

    foreach ($head as $i => $cell) {
        $w = $widths[$i] ?? null;
        $html .= '<th'.($w ? ' style="width:'.$w.'"' : '').'>'.$cell.'</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';

        foreach ($row as $cell) {
            $html .= '<td>'.$cell.'</td>';
        }

        $html .= '</tr>';
    }

    return $html.'</tbody></table>';
}

/**
 * @param  array<string, Svg>  $diagrams
 */
function documentHtml(array $diagrams, string $svgDir): string
{
    /** Gambar diagram beserta keterangannya, pada ukuran aslinya (1 satuan = 1 pt). */
    $fig = function (string $key, string $caption) use ($diagrams, $svgDir): string {
        $svg = $diagrams[$key];

        return '<div class="fig"><img src="'.$svgDir.'/'.$key.'.svg" '
            .'style="width:'.$svg->w.'pt;height:'.$svg->h.'pt"></div>'
            .'<p class="cap">'.$caption.'</p>';
    };

    /** Kepala bagian: label kecil, judul, garis. */
    $head = fn (string $eyebrow, string $title) => '<p class="eyebrow">'.$eyebrow.'</p>'
        .'<h2>'.$title.'</h2><div class="rule"></div>';

    $css = <<<'CSS'
    @page { margin: 34pt 42pt 42pt 42pt; }

    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.38; color: #1e293b; margin: 0; }

    h1 { font-size: 27pt; line-height: 1.15; margin: 0 0 6pt; color: #0f172a; }
    h2 { font-size: 14pt; line-height: 1.2; margin: 0 0 3pt; color: #0f172a; }
    h3 { font-size: 10pt; margin: 9pt 0 3pt; color: #0f172a; }

    p { margin: 0 0 6pt; text-align: justify; }
    ul { margin: 0 0 6pt; padding-left: 13pt; }
    li { margin-bottom: 2pt; text-align: justify; }

    .page { page-break-after: always; }
    .last { page-break-after: auto; }

    .eyebrow { font-size: 7.6pt; letter-spacing: 1.4pt; color: #0284c7; margin: 0 0 4pt; }
    .rule { height: 2.4pt; background: #0284c7; width: 60pt; margin: 0 0 9pt; }

    .fig { margin: 0 0 3pt; }
    .cap { font-size: 7.4pt; color: #64748b; margin: 0 0 5pt; text-align: left; }

    table { width: 100%; border-collapse: collapse; margin: 0 0 8pt; font-size: 8pt; }
    th { background: #f1f5f9; color: #0f172a; text-align: left; padding: 3.5pt 6pt;
         border-bottom: 1pt solid #cbd5e1; font-weight: normal; }
    td { padding: 3.5pt 6pt; border-bottom: 0.6pt solid #e2e8f0; vertical-align: top; }

    .box { background: #f8fafc; border: 0.8pt solid #e2e8f0; border-left: 2.6pt solid #0284c7;
           padding: 7pt 10pt; margin: 0 0 8pt; font-size: 8.4pt; }
    .box b { color: #0c4a6e; }

    .where { background: #fffbeb; border: 0.8pt solid #fcd34d; padding: 5pt 9pt;
             font-size: 8pt; color: #78350f; margin: 0 0 6pt; }

    /* Sampul */
    .cover { background: #0f172a; color: #e2e8f0; padding: 44pt 38pt; margin: -6pt 0 14pt; }
    .cover h1 { color: #ffffff; }
    .cover .sub { color: #7dd3fc; font-size: 11pt; margin: 0 0 20pt; }
    .cover .meta { color: #94a3b8; font-size: 8.4pt; line-height: 1.7; }
    .cover .tag { font-size: 7.6pt; letter-spacing: 2pt; color: #38bdf8; margin: 0 0 12pt; }

    .toc { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 0 0 4pt; }
    .toc td { padding: 4.5pt 0; border-bottom: 0.6pt solid #e2e8f0; }
    .toc .n { width: 30pt; color: #0284c7; }
    .toc .d { color: #64748b; font-size: 7.8pt; }
    .toc .p { width: 26pt; text-align: right; color: #64748b; vertical-align: top; }
    CSS;

    $html = '<html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>';

    /* ------------------------------------------------------------- sampul */

    $html .= '<div class="page">'
        .'<div class="cover">'
        .'<p class="tag">DOKUMENTASI PROSES BISNIS</p>'
        .'<h1>Alur Kerja Aplikasi HRIS</h1>'
        .'<p class="sub">Jadwal · Absensi · Cuti · Lembur · Laporan</p>'
        .'<p class="meta">Cahaya Optima Karya<br>Sembilan diagram alur, disusun dari perilaku aplikasi yang sedang berjalan.<br>Disusun 1 September 2026</p>'
        .'</div>'
        .'<h3>Untuk siapa dokumen ini</h3>'
        .'<p>Dokumen ini menjelaskan bagaimana kelima proses inti HRIS berjalan dari ujung ke ujung: siapa mengerjakan apa, keputusan apa yang diambil di tiap persimpangan, dan bagian mana yang dikerjakan sistem tanpa campur tangan orang. Cocok dipakai HR sebagai acuan kerja harian, pimpinan untuk memahami jalur persetujuan, dan tim pengembang sebagai peta perilaku aplikasi.</p>'
        .'<p>Setiap bagian berisi satu diagram alur, ringkasan singkat, dan daftar aturan yang berlaku pada proses tersebut. Nama menu aplikasi ditulis apa adanya agar mudah dicocokkan dengan layar.</p>'
        .'<div class="box"><b>Satu hal yang menyatukan semuanya.</b> Jadwal menentukan apa yang <i>seharusnya</i> terjadi pada satu hari; absensi mencatat apa yang <i>benar-benar</i> terjadi; cuti, lembur, koreksi, dan tukar jadwal adalah cara mengubah salah satu dari keduanya; laporan membaca hasil akhirnya. Karena itu setiap persetujuan yang berhasil selalu berakhir dengan hal yang sama: status hari yang bersangkutan dihitung ulang.</div>'
        .'</div>';

    /* --------------------------------------------------------- daftar isi */

    $toc = [
        ['—', 'peta', 'Peta alur menyeluruh', 'Bagaimana kelima modul saling menyambung'],
        ['1', 'jadwal', 'Jadwal kerja', 'Dari shift dan pola jadwal menjadi roster harian'],
        ['1b', 'tukar', 'Tukar jadwal', 'Tukar shift, minta digantikan, dan tukar hari libur'],
        ['2', 'punch', 'Absensi — dari mesin ke jam kerja', 'Perjalanan satu punch sidik jari'],
        ['2b', 'resolver', 'Absensi — penentuan status harian', 'Urutan pemeriksaan yang menetapkan status'],
        ['2c', 'koreksi', 'Koreksi absensi dan absen mandiri', 'Ketika jam yang tercatat perlu diperbaiki'],
        ['3', 'cuti', 'Cuti dan izin', 'Pengajuan, persetujuan satu tahap, dan dampaknya ke absensi'],
        ['4', 'lembur', 'Lembur', 'Pengajuan karyawan dan persetujuan atasan'],
        ['5', 'laporan', 'Laporan', 'Empat rekap dan cara datanya disaring'],
        ['A', 'lampiran-a', 'Lampiran — daftar status', 'Arti tiap status yang muncul di layar'],
        ['B', 'lampiran-b', 'Lampiran — tugas otomatis', 'Pekerjaan yang berjalan sendiri tiap hari'],
    ];

    $tocRows = '';

    foreach ($toc as [$n, $key, $title, $desc]) {
        $tocRows .= '<tr><td class="n">'.$n.'</td>'
            .'<td>'.$title.'<br><span class="d">'.$desc.'</span></td>'
            .'<td class="p">'.pageOf($key).'</td></tr>';
    }

    $html .= '<div class="page">'
        .$head('DAFTAR ISI', 'Isi dokumen')
        .'<table class="toc">'.$tocRows.'</table>'
        .'<h3>Kelima proses dalam satu kalimat</h3>'
        .table(
            ['Proses', 'Intinya'],
            [
                ['Jadwal', 'HR menugaskan pola jadwal; sistem menerjemahkannya menjadi shift atau libur untuk tiap tanggal tiap karyawan.'],
                ['Absensi', 'Punch mesin, absen mandiri, atau input HR menjadi jam masuk dan jam pulang, lalu diadu dengan jadwal untuk menghasilkan satu status per hari.'],
                ['Cuti', 'Karyawan mengajukan, atasan memutuskan sekali dan final, hari-hari yang disetujui berubah statusnya di absensi.'],
                ['Lembur', 'Karyawan mengajukan untuk hari yang sudah dijalani, atasan menyetujui jumlah menitnya, HR merekap untuk penggajian.'],
                ['Laporan', 'Empat rekap membaca data yang sama dengan penyaringan yang sama, sehingga angkanya tidak pernah berbeda antar layar.'],
            ],
            ['16%', '84%'],
        )
        .'</div>';

    /* ----------------------------------------------------- cara membaca */

    $html .= '<div class="page">'
        .$head('PANDUAN SINGKAT', 'Cara membaca diagram')
        .'<p>Semua diagram dalam dokumen ini memakai lambang yang sama. <b>Bentuk</b> menunjukkan jenis langkah, <b>warna</b> menunjukkan siapa yang mengerjakannya.</p>'
        .'<h3>Bentuk</h3>'
        .table(
            ['Bentuk', 'Artinya'],
            [
                ['Kotak sudut membulat', 'Satu langkah kerja atau satu keadaan.'],
                ['Belah ketupat', 'Persimpangan keputusan. Panah keluarnya diberi label Ya / Tidak.'],
                ['Jajar genjang', 'Data yang tersimpan — tabel di basis data, berkas, atau dokumen.'],
                ['Kotak lonjong', 'Titik awal atau titik akhir sebuah alur.'],
                ['Kotak kuning bergaris tebal di kiri', 'Catatan: aturan yang berlaku, bukan langkah kerja.'],
                ['Garis putus-putus', 'Sesuatu yang dicatat atau diberitahukan di samping alur utama, bukan langkah yang harus dilalui.'],
            ],
            ['32%', '68%'],
        )
        .'<h3>Warna</h3>'
        .table(
            ['Warna', 'Pelakunya'],
            [
                ['Biru', 'Karyawan yang bersangkutan.'],
                ['Ungu', 'Atasan langsung atau rekan kerja yang dimintai persetujuan.'],
                ['Jingga', 'HR atau petugas dengan hak setara.'],
                ['Hijau', 'Sistem sendiri, tanpa campur tangan orang.'],
                ['Kuning', 'Persimpangan keputusan.'],
                ['Merah', 'Alur berhenti: pengajuan ditolak atau tidak bisa dilanjutkan.'],
                ['Abu-abu', 'Data yang tersimpan, atau titik awal dan akhir alur.'],
            ],
            ['32%', '68%'],
        )
        .'<h3>Jalur pelaku</h3>'
        .'<p>Pada alur yang melibatkan lebih dari satu pihak — cuti, lembur, koreksi, dan tukar jadwal — diagram dibagi menjadi kolom berjudul. Letak sebuah kotak menunjukkan siapa yang mengerjakan langkah itu, dan panah yang menyeberang kolom berarti pekerjaan berpindah tangan.</p>'
        .'</div>';

    /* --------------------------------------------------------- peta besar */

    $html .= '<div class="page">'
        .$head('PETA MENYELURUH', 'Bagaimana kelima modul saling menyambung')
        .'<p>Alirannya berjalan dari atas ke bawah. Data induk disiapkan sekali; jadwal menyusun hari kerja tiap orang; pengajuan mengubah rencana hari tertentu; absensi menetapkan apa yang tercatat; laporan merangkum semuanya. Dua panah berwarna menandai umpan balik: tukar jadwal yang disetujui kembali menjadi baris roster, dan cuti atau koreksi yang disetujui mengubah status absensi yang sudah tercatat.</p>'
        .$fig('peta', 'Gambar 1 — Peta alur menyeluruh aplikasi HRIS.')
        .'</div>';

    /* ------------------------------------------------------------- jadwal */

    $html .= '<div class="page">'
        .$head('BAGIAN 1', 'Jadwal kerja')
        .'<p>Jadwal adalah dasar seluruh perhitungan absensi: tanpa shift yang terjadwal, sistem tidak tahu seseorang terlambat, pulang cepat, atau justru sedang libur. Prosesnya dimulai dari HR yang menyiapkan shift dan pola jadwal, lalu menugaskan pola itu ke karyawan; sisanya dikerjakan sistem.</p>'
        .$fig('jadwal', 'Gambar 2 — Dari shift dan pola jadwal menjadi roster harian.')
        .'</div>';

    $html .= '<div class="page">'
        .$head('BAGIAN 1 · LANJUTAN', 'Aturan jadwal kerja')
        .ul([
            'Karyawan yang ditandai mengikuti jam kantor tidak pernah dibuatkan baris roster. Jadwalnya diturunkan dari pola miliknya sendiri, atau — bila belum diatur — dari pola bawaan aplikasi yang dipilih di Pengaturan.',
            'Baris roster bersumber <b>Manual</b> tidak pernah ditimpa generator. Ini berlaku untuk override harian, hasil impor Excel, maupun hasil tukar jadwal yang disetujui.',
            'Bila satu tanggal tercakup dua penugasan pola sekaligus, yang berlaku adalah penugasan yang <b>paling baru dibuat</b> — bukan yang tanggal mulainya paling akhir. Penugasan lama tetap menguasai tanggal-tanggal yang tidak dicakup penugasan baru, sehingga masa penggantian yang pendek mengembalikan kendali dengan sendirinya setelah berakhir.',
            'Menonaktifkan atau mengarsipkan sebuah pola tidak mengubah jadwal orang yang sudah memakainya. Penandaan itu hanya menentukan pola apa yang boleh dipilih ke depan.',
            'Setiap perubahan roster langsung memicu penghitungan ulang absensi yang sudah tercatat pada rentang tersebut. Tanggal yang belum punya baris absensi dibiarkan untuk penutupan harian.',
            'Status WFH pada roster hanya berlaku di hari kerja. Hari libur tidak bisa sekaligus menjadi hari WFH.',
        ])
        .'<h3>Dua bentuk pola jadwal</h3>'
        .table(
            ['Bentuk', 'Cara kerjanya', 'Cocok untuk'],
            [
                ['Mingguan Tetap', 'Shift ditentukan per hari Senin sampai Minggu, lalu berulang setiap pekan.', 'Jam kantor, satpam dengan pembagian hari tetap, staf toko dengan hari libur tetap.'],
                ['Rotasi', 'Shift berputar dalam siklus sejumlah hari, dihitung dari satu tanggal jangkar, tanpa terikat hari dalam seminggu.', 'Regu kerja yang berputar pagi–siang–malam–libur.'],
            ],
            ['16%', '46%', '38%'],
        )
        .'<h3>Empat sumber baris roster</h3>'
        .table(
            ['Sumber', 'Dibuat oleh', 'Bisa ditimpa generator?'],
            [
                ['Generator dari pola', 'Sistem, saat pola ditugaskan dan tiap dini hari', 'Ya'],
                ['Override harian', 'HR, satu tanggal satu karyawan', 'Tidak'],
                ['Impor roster Excel', 'HR, satu bulan sekaligus', 'Tidak'],
                ['Tukar jadwal disetujui', 'Sistem, setelah HR menyetujui', 'Tidak'],
            ],
            ['30%', '44%', '26%'],
        )
        .'<div class="where"><b>Di aplikasi:</b> Absensi › Shift, Pola Jadwal, Jadwal Kerja, dan Belum Terjadwal. Karyawan melihat hasilnya di menu Jadwal Saya.</div>'
        .'</div>';

    /* -------------------------------------------------------- tukar jadwal */

    $html .= '<div class="page">'
        .$head('BAGIAN 1B', 'Tukar jadwal')
        .'<p>Karyawan dapat mengusulkan perubahan jadwalnya sendiri, tetapi karena perubahan itu menyangkut orang lain, persetujuannya melewati dua tahap: rekan yang diminta lebih dulu, baru HR. Hasil akhirnya ditulis sebagai override manual pada roster kedua belah pihak.</p>'
        .$fig('tukar-jadwal', 'Gambar 3 — Pengajuan tukar jadwal dan dua tahap persetujuannya.')
        .'<h3>Aturan yang berlaku</h3>'
        .ul([
            'Tersedia tiga bentuk pengajuan: <b>tukar shift</b> dengan rekan pada dua tanggal, <b>minta digantikan</b> pada satu tanggal, dan <b>tukar hari libur</b>.',
            'Bentrok jadwal diperiksa dua kali — saat pengajuan dibuat dan sekali lagi saat HR memutuskan — karena roster bisa berubah di antara keduanya.',
            'Bukti berupa gambar boleh dilampirkan. Berkasnya disimpan di penyimpanan privat dan hanya bisa dibuka pihak yang terlibat serta petugas yang berwenang.',
            'Pengaju dapat membatalkan selama belum ada keputusan HR.',
        ])
        .'<div class="where"><b>Di aplikasi:</b> karyawan mengajukan dari menu Jadwal Saya; HR memutuskan di Absensi › Tukar Jadwal, termasuk persetujuan sekaligus untuk beberapa baris.</div>'
        .'</div>';

    /* -------------------------------------------------------------- punch */

    $html .= '<div class="page">'
        .$head('BAGIAN 2', 'Absensi — dari mesin ke jam kerja')
        .'<p>Mesin sidik jari tidak menunggu diminta: setiap kali ada yang menempelkan jari, mesin mengirim sendiri catatannya ke aplikasi. Tugas aplikasi adalah menyimpannya tanpa ganda, mengenali pemiliknya, lalu memutuskan punch mana yang menjadi jam masuk dan jam pulang.</p>'
        .$fig('punch', 'Gambar 4 — Perjalanan satu punch sidik jari sampai menjadi jam masuk dan jam pulang.')
        .'<h3>Aturan yang berlaku</h3>'
        .ul([
            'Hanya mesin yang nomor serialnya terdaftar yang datanya diterima, dan sambungannya harus melalui HTTPS saat dipakai sungguhan.',
            'Pemetaan PIN boleh khusus untuk satu mesin atau berlaku umum; bila keduanya ada, yang khusus mesin menang. Saat sebuah PIN dipetakan, punch lama dengan PIN itu ikut ditarik dan hari-harinya dihitung ulang.',
            'Bila tidak ada satu pun punch pada jendela hari itu, baris absensi yang sudah ada dibiarkan apa adanya — supaya jam yang diisi manual tidak terhapus.',
        ])
        .'<div class="where"><b>Di aplikasi:</b> Absensi › Mesin Absensi (daftar dan pemantauan) serta Data Punch (punch yang belum terpetakan).</div>'
        .'</div>';

    /* ----------------------------------------------------------- resolver */

    $html .= '<div class="page">'
        .$head('BAGIAN 2B', 'Absensi — penentuan status harian')
        .'<p>Apa pun sumber jamnya, penentuan status satu karyawan pada satu tanggal selalu melewati urutan pemeriksaan yang sama. Urutannya penting: yang di atas mengalahkan yang di bawah.</p>'
        .$fig('resolver', 'Gambar 5 — Urutan pemeriksaan yang menetapkan status satu karyawan pada satu tanggal.')
        .'<div class="where"><b>Kapan pemeriksaan ini dijalankan.</b> Untuk karyawan yang menempelkan sidik jari, seketika saat mesin mengirim datanya; sisanya — yang tidak hadir, yang sedang cuti, dan hari libur — diisi tugas terjadwal pukul 01.30. Hari yang tampil sebagai Libur padahal orangnya masuk hampir selalu berpangkal pada jadwal, bukan pada absensinya: tanggal itu tidak punya shift terjadwal.</div>'
        .'</div>';

    /* ------------------------------------------- koreksi & absen mandiri */

    $html .= '<div class="page">'
        .$head('BAGIAN 2C', 'Koreksi absensi dan absen mandiri')
        .'<p>Dua hal yang menjaga absensi tetap benar ketika mesin bukan jawabannya: koreksi untuk jam yang telanjur salah, dan absen mandiri untuk hari kerja yang memang tidak dijalani di kantor.</p>'
        .$fig('koreksi', 'Gambar 6 — Pengajuan koreksi absensi dan persetujuannya.')
        .'<h3>Koreksi absensi</h3>'
        .ul([
            'Petugas tidak boleh menyetujui koreksi miliknya sendiri.',
            'Beberapa koreksi bisa disetujui sekaligus lewat daftar centang; tiap baris tetap melewati pemeriksaan yang sama.',
            'Perubahan jam dan status koreksi dikerjakan dalam satu transaksi, sehingga tidak mungkin ada jam yang sudah berubah tetapi koreksinya masih berstatus Menunggu.',
            'Karyawan dapat menarik pengajuannya selama masih berstatus Menunggu.',
        ])
        .'<h3>Absen mandiri untuk WFH dan dinas luar</h3>'
        .ul([
            'Panel absen mandiri hanya muncul pada hari yang memang berstatus kerja jarak jauh: WFH menurut jadwal, atau pengajuan WFH / dinas luar yang sudah disetujui.',
            'Setiap absen masuk dan absen pulang wajib disertai swafoto dan titik lokasi yang diambil saat itu juga. Absen pulang tidak bisa dilakukan sebelum absen masuk.',
            'Foto disimpan di penyimpanan privat; hanya karyawan yang bersangkutan dan petugas berwenang yang bisa membukanya. Foto lewat enam bulan dipangkas otomatis — datanya tetap ada, hanya gambarnya yang dibuang.',
            'Bukti foto dan koordinat tidak ikut dihitung ulang saat HR memproses ulang hari itu, jadi tidak akan hilang.',
        ])
        .'<div class="where"><b>Di aplikasi:</b> karyawan memakai menu Absensi Saya; HR memutuskan di Absensi › Koreksi Absensi dan memantau lokasi di Absensi › Peta Absen.</div>'
        .'</div>';

    /* --------------------------------------------------------------- cuti */

    $html .= '<div class="page">'
        .$head('BAGIAN 3', 'Cuti dan izin')
        .'<p>Persetujuan cuti berjalan satu tahap: keputusan atasan langsung bersifat final. HR tetap bisa memutuskan, tetapi sebagai jalan keluar — bukan tahap kedua — bagi karyawan yang belum punya atasan.</p>'
        .$fig('cuti', 'Gambar 7 — Pengajuan cuti, persetujuan satu tahap, dan dampaknya ke absensi.')
        .'</div>';

    $html .= '<div class="page">'
        .$head('BAGIAN 3 · LANJUTAN', 'Aturan cuti dan izin')
        .ul([
            'Dua pemeriksaan dijalankan sebelum pengajuan diterima: tanggalnya tidak boleh beririsan dengan pengajuan lain yang masih berlaku, dan sisa saldo harus cukup bila jenis cutinya memotong saldo.',
            'Pengajuan yang masih menunggu maupun yang sudah disetujui sama-sama menahan kuota, sehingga saldo yang sama tidak bisa dipesan dua kali.',
            'Kuota tahunan diambil dari jatah khusus karyawan bila ada; bila tidak, dari jatah bawaan jenis cutinya.',
            'Cuti yang sudah disetujui bersifat final. Pembatalan mengembalikan hari-harinya ke status berdasarkan punch, dan hanya HR yang dapat melakukannya. Pengajuan hanya boleh dihapus oleh karyawan yang mengajukannya.',
            'Lampiran disimpan di penyimpanan privat dengan nama berkas acak; nama asli dari pengunggah tidak pernah dipakai sebagai nama berkas di penyimpanan.',
            'Penghitungan ulang absensi hanya menyentuh hari sampai dengan hari ini. Hari cuti yang masih di masa depan diisi oleh penutupan absensi otomatis pada tanggalnya sendiri.',
        ])
        .'<h3>Dampak jenis cuti pada absensi</h3>'
        .table(
            ['Sifat jenis cuti', 'Status hari itu', 'Jam kerja & lembur', 'Absen mandiri'],
            [
                ['WFH', 'WFH', 'Tetap dihitung dari shift', 'Wajib, dengan swafoto & lokasi'],
                ['Dinas Luar', 'Dinas Luar', 'Tetap dihitung dari shift', 'Wajib, dengan swafoto & lokasi'],
                ['Cuti atau izin', 'Cuti / Izin', 'Nol jam', 'Tidak ada'],
                ['Sakit', 'Sakit', 'Nol jam', 'Tidak ada'],
            ],
            ['22%', '20%', '32%', '26%'],
        )
        .'<div class="box"><b>Mengapa WFH dan dinas luar diperlakukan berbeda.</b> Keduanya diajukan lewat formulir yang sama dengan cuti, tetapi artinya berlawanan: orangnya justru sedang bekerja. Karena itu keduanya tidak menghentikan perhitungan shift — keterlambatan, jam kerja, dan lembur tetap dihitung seperti hari kerja biasa, hanya labelnya yang dipertahankan agar terlihat bahwa hari itu tidak dijalani dari kantor.</div>'
        .'<h3>Contoh perhitungan saldo</h3>'
        .'<div class="box">Kuota cuti tahunan seorang karyawan 12 hari. Ia sudah mengambil 5 hari yang disetujui, dan punya 2 hari yang masih menunggu keputusan atasan.'
        .'<br><br>Sisa saldo yang dipakai sistem = 12 − 5 − 2 = <b>5 hari</b>. Pengajuan baru selama 6 hari akan ditolak formulirnya, meskipun yang 2 hari tadi belum tentu disetujui — kuota ditahan sejak diajukan agar tidak dipesan dua kali. Bila pengajuan 2 hari itu kemudian ditolak atau dibatalkan, saldonya kembali menjadi 7 hari.</div>'
        .'<div class="where"><b>Di aplikasi:</b> karyawan dan atasan memakai menu Cuti Saya; HR mengelola di Absensi › Cuti, Jenis Cuti, dan Saldo Cuti.</div>'
        .'</div>';

    /* ------------------------------------------------------------- lembur */

    $html .= '<div class="page">'
        .$head('BAGIAN 4', 'Lembur')
        .'<p>Lembur diajukan sendiri oleh karyawan untuk hari yang sudah dijalani, lalu disetujui atasan langsungnya. HR tidak ikut memutuskan — perannya memantau dan merekap menit yang sudah disetujui sebagai bahan penggajian.</p>'
        .$fig('lembur', 'Gambar 8 — Pengajuan lembur karyawan dan persetujuan atasan.')
        .'</div>';

    $html .= '<div class="page">'
        .$head('BAGIAN 4 · LANJUTAN', 'Aturan lembur')
        .ul([
            'Karyawan yang atasan langsungnya belum diatur tidak bisa mengajukan lembur; sistem meminta menghubungi HR lebih dulu.',
            'Tanggal lembur paling lambat hari ini — lembur tidak bisa diajukan untuk hari yang belum terjadi.',
            'Durasi harus lebih dari nol menit dan tidak lebih dari 12 jam. Jam selesai yang lebih awal daripada jam mulai dibaca sebagai melewati tengah malam, misalnya 22.00 sampai 01.00 berarti 180 menit.',
            'Hanya boleh ada satu pengajuan yang menunggu atau disetujui untuk satu tanggal.',
            'Menit hasil hitung sistem mengikuti pengaturan shift: menit lewat jam pulang dikurangi ambang “lembur dihitung setelah”, dan dianggap nol bila belum mencapai durasi minimum lembur.',
            'Atasan boleh menyetujui dengan jumlah menit lebih kecil daripada yang diajukan. Yang masuk rekap adalah menit yang disetujui, bukan yang diajukan.',
            'Tidak seorang pun boleh memutuskan pengajuan lemburnya sendiri.',
            'Karyawan dapat membatalkan selama masih menunggu, dan atasannya diberi tahu sebelum datanya hilang.',
        ])
        .'<h3>Tiga angka menit yang dicatat</h3>'
        .table(
            ['Angka', 'Dari mana', 'Dipakai untuk'],
            [
                ['Menit diajukan', 'Selisih jam mulai dan jam selesai yang diisi karyawan.', 'Usulan yang dinilai atasan.'],
                ['Menit hasil hitung', 'Menit lembur yang dihitung sistem dari jam pulang dan jendela shift hari itu.', 'Pembanding bagi atasan saat menilai usulan.'],
                ['Menit disetujui', 'Angka yang ditetapkan atasan saat menyetujui.', 'Satu-satunya angka yang masuk rekap dan penggajian.'],
            ],
            ['20%', '46%', '34%'],
        )
        .'<div class="where"><b>Di aplikasi:</b> karyawan dan atasan memakai menu Lembur Saya; HR memantau di Absensi › Lembur dan Rekap Lembur.</div>'
        .'<h3>Contoh satu hari</h3>'
        .'<div class="box">Shift <b>08.00–17.00</b>, istirahat 60 menit, lembur dihitung setelah 30 menit lewat jam pulang, dengan durasi minimum 60 menit.'
        .'<br><br>Karyawan absen masuk 07.55 dan absen pulang 20.10.'
        .'<br>• <b>Jam kerja</b> = 12 jam 15 menit dikurangi 60 menit istirahat = <b>11 jam 15 menit</b>.'
        .'<br>• <b>Menit hasil hitung</b> = 190 menit lewat jam pulang, dikurangi ambang 30 menit = <b>160 menit</b>. Sudah melampaui durasi minimum, jadi dihitung.'
        .'<br>• Karyawan mengajukan lembur 17.00–20.00 = <b>180 menit diajukan</b>, dengan uraian pekerjaannya.'
        .'<br>• Atasan melihat pembanding 160 menit dan menyetujui <b>160 menit</b>.'
        .'<br><br>Yang masuk Rekap Lembur bulan itu adalah 160 menit — angka yang disetujui.</div>'
        .'</div>';

    /* ------------------------------------------------------------ laporan */

    $html .= '<div class="page">'
        .$head('BAGIAN 5', 'Laporan')
        .'<p>Semua laporan membaca data yang sama dan disaring dengan cara yang sama, sehingga angkanya tidak pernah berbeda antar layar. Yang membedakan hanyalah sudut pandangnya: per karyawan, per hari, per jenis cuti, atau per menit lembur.</p>'
        .$fig('laporan', 'Gambar 9 — Sumber data, penyaringan, empat laporan, dan bentuk keluarannya.')
        .'</div>';

    $html .= '<div class="page">'
        .$head('BAGIAN 5 · LANJUTAN', 'Empat laporan dan aturannya')
        .table(
            ['Laporan', 'Periode', 'Isi ringkas'],
            [
                ['Rekap Kehadiran', 'Bulanan', 'Per karyawan: total hari kerja terjadwal, hadir, terlambat, pulang cepat, alfa, cuti, sakit, total menit terlambat, menit kerja, dan menit lembur.'],
                ['Log Absensi Harian', 'Bulanan', 'Satu baris per karyawan per tanggal beserta jam masuk, jam pulang, dan statusnya, ditambah ringkasan seluruh periode.'],
                ['Rekap Cuti', 'Tahunan', 'Per jenis cuti: kuota, jumlah yang sudah terpakai, dan sisa saldo tiap karyawan.'],
                ['Rekap Lembur', 'Bulanan', 'Jumlah hari lembur dan total menit yang disetujui atasan, diurutkan dari yang terbesar.'],
            ],
            ['22%', '13%', '65%'],
        )
        .'<h3>Aturan yang berlaku</h3>'
        .ul([
            'Yang dilihat seseorang dibatasi cakupan datanya: bisa seluruh perusahaan, satu cabang, satu divisi, atau hanya bawahannya sendiri. Batas ini berlaku sama di layar maupun di berkas yang diunduh.',
            'Hak melihat dan hak mengekspor tiap laporan diatur terpisah, sehingga seseorang bisa diberi hak membaca rekap tanpa diberi hak mengunduhnya.',
            'Kolom “hadir” memakai satu definisi bersama: Hadir, Terlambat, Pulang Cepat, WFH, dan Dinas Luar semuanya terhitung bekerja.',
            'Kolom “total hari” hanya menghitung hari kerja terjadwal; libur nasional dan libur jadwal tidak ikut, agar rincian hadir, alfa, cuti, dan sakit berdamai dengan totalnya.',
            'Karyawan aktif yang belum punya satu pun baris absensi tetap muncul dengan angka nol, bukan hilang dari rekap.',
            'Ringkasan dihitung dari seluruh periode, bukan hanya dari halaman yang sedang tampil.',
        ])
        .'<div class="where"><b>Di aplikasi:</b> menu Laporan — Rekap Kehadiran, Log Absensi, dan Rekap Cuti; Rekap Lembur ada di Absensi › Rekap Lembur.</div>'
        .'<h3>Urutan yang disarankan saat menutup bulan</h3>'
        .'<div class="box"><b>1.</b> Bereskan sisa pengajuan yang masih menunggu — cuti, lembur, koreksi, dan tukar jadwal — karena semuanya mengubah angka rekap.'
        .'<br><b>2.</b> Periksa daftar punch yang belum terpetakan; punch bertuan yang baru dipetakan langsung menghitung ulang hari-harinya.'
        .'<br><b>3.</b> Buka Log Absensi Harian dan telusuri baris berstatus Alfa. Alfa yang keliru hampir selalu berarti hari itu tidak terjadwal, atau punch-nya belum terpetakan.'
        .'<br><b>4.</b> Setelah bersih, unduh Rekap Kehadiran dan Rekap Lembur sebagai bahan penggajian, lalu simpan berkasnya sebagai arsip bulan tersebut.</div>'
        .'</div>';

    /* --------------------------------------------------------- lampiran A */

    $html .= '<div class="page">'
        .$head('LAMPIRAN A', 'Daftar status dan artinya')
        .'<h3>Status kehadiran harian</h3>'
        .table(
            ['Status', 'Arti', 'Terhitung bekerja'],
            [
                ['Hadir', 'Masuk dan pulang dalam batas toleransi shift.', 'Ya'],
                ['Terlambat', 'Jam masuk melewati batas toleransi keterlambatan shift.', 'Ya'],
                ['Pulang Cepat', 'Jam pulang lebih awal daripada batas toleransi shift.', 'Ya'],
                ['WFH', 'Bekerja dari rumah menurut jadwal atau pengajuan yang disetujui.', 'Ya'],
                ['Dinas Luar', 'Bekerja di luar kantor menurut pengajuan yang disetujui.', 'Ya'],
                ['Alfa', 'Ada shift terjadwal, tetapi tidak ada jam masuk sama sekali.', 'Tidak'],
                ['Cuti / Izin', 'Ada pengajuan cuti atau izin yang sudah disetujui.', 'Tidak'],
                ['Sakit', 'Pengajuan bertipe sakit yang sudah disetujui.', 'Tidak'],
                ['Libur Nasional', 'Tanggal merah yang berlaku untuk cabang tersebut. Jam kerja pada hari ini dihitung penuh sebagai lembur.', 'Tidak'],
                ['Libur', 'Hari libur sesuai jadwal kerja karyawan.', 'Tidak'],
            ],
            ['18%', '64%', '18%'],
        )
        .'<h3>Urutan status pengajuan</h3>'
        .table(
            ['Jenis pengajuan', 'Urutan status'],
            [
                ['Cuti / izin', 'Menunggu Atasan <i>(atau Menunggu HR bila belum punya atasan)</i> → Disetujui, Ditolak, atau Dibatalkan'],
                ['Lembur', 'Menunggu → Disetujui atau Ditolak'],
                ['Koreksi absensi', 'Menunggu → Disetujui atau Ditolak'],
                ['Tukar jadwal', 'Menunggu Rekan → Menunggu HR → Disetujui, Ditolak, atau Dibatalkan'],
            ],
            ['24%', '76%'],
        )
        .'<h3>Sumber baris jadwal</h3>'
        .table(
            ['Sumber', 'Arti'],
            [
                ['Otomatis', 'Dibuat generator dari pola jadwal. Boleh ditimpa generator berikutnya.'],
                ['Manual', 'Ditulis tangan, hasil impor, atau hasil tukar jadwal. Tidak pernah ditimpa generator.'],
            ],
            ['24%', '76%'],
        )
        .'</div>';

    /* --------------------------------------------------------- lampiran B */

    $html .= '<div class="last">'
        .$head('LAMPIRAN B', 'Tugas otomatis yang berjalan sendiri')
        .'<p>Sebagian alur dalam dokumen ini tidak dipicu oleh siapa pun. Pekerjaan berikut berjalan sendiri menurut jadwalnya, dan aman bila kebetulan berjalan dua kali.</p>'
        .table(
            ['Waktu', 'Pekerjaan', 'Akibatnya pada alur'],
            [
                ['00.05', 'Menonaktifkan karyawan yang masa kontraknya sudah berakhir', 'Karyawan tidak lagi ikut diproses absensinya.'],
                ['00.10', 'Memangkas log komunikasi mesin absensi', 'Menyimpan 14 hari terakhir saja.'],
                ['00.15', 'Memangkas notifikasi yang sudah dibaca', 'Menyimpan 30 hari terakhir saja.'],
                ['00.20', 'Memperpanjang roster dari pola dan penugasan yang aktif', 'Jadwal selalu tersedia ke depan tanpa perlu digenerate manual tiap bulan.'],
                ['01.30', 'Menutup absensi hari sebelumnya untuk seluruh karyawan aktif', 'Yang tidak punya punch ditandai Alfa; cuti yang disetujui dan hari libur diisi sesuai urutan pemeriksaan.'],
                ['06.00', 'Mengingatkan HR tentang kontrak yang akan berakhir', 'Pengingat pada H-30, H-14, dan H-7.'],
                ['Tiap 15 menit', 'Memeriksa mesin absensi yang berhenti mengirim data', 'HR diberi tahu sekali per gangguan.'],
                ['Tanggal 1, 00.25', 'Memangkas swafoto absen mandiri yang sudah lewat 6 bulan', 'Data absensinya tetap ada, hanya gambarnya yang dibuang.'],
                ['Tanggal 1, 02.10', 'Memangkas jejak aktivitas pengguna', 'Menjaga tabel jejak audit tetap ramping.'],
            ],
            ['16%', '38%', '46%'],
        )
        .'<div class="box"><b>Catatan untuk operasional.</b> Penutupan absensi pukul 01.30 adalah pengaman, bukan jalur utama. Karyawan yang menempelkan sidik jari sudah dihitung statusnya seketika saat mesin mengirim datanya; tugas dini hari itu yang mengurus sisanya — orang yang tidak hadir, yang sedang cuti, dan hari libur. Tombol <i>Proses Ulang</i> pada papan Absensi Harian menjalankan pekerjaan yang persis sama untuk satu tanggal, terbatas pada karyawan yang tampil di layar pemakainya.</div>'
        .'<div class="box"><b>Kalau ada yang terlihat janggal.</b> Status yang tidak masuk akal hampir selalu berpangkal pada jadwal, bukan pada absensinya: tanggal yang tidak terjadwal akan tampil sebagai Libur, dan karyawan yang belum pernah ditugaskan pola tidak punya baris roster sama sekali. Daftar <i>Belum Terjadwal</i> pada menu Jadwal Kerja adalah tempat pertama yang perlu diperiksa.</div>'
        .'</div>';

    return $html.'</body></html>';
}
