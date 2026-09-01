<?php

/**
 * Membangun docs/Alur-Kerja-HRIS.pdf.
 *
 *   php docs/render-workflow.php
 *
 * Diagram digambar lebih dulu sebagai berkas SVG di docs/workflow/svg, lalu
 * ditempel ke dokumen. Dompdf tidak membaca <svg> inline — hanya <img src>
 * yang menunjuk ke berkas SVG — jadi berkas perantara itu memang diperlukan,
 * sekaligus memudahkan memeriksa satu diagram tanpa membangun ulang seluruh PDF.
 */

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/workflow/diagrams.php';
require __DIR__.'/workflow/document.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$svgDir = __DIR__.'/workflow/svg';

if (! is_dir($svgDir)) {
    mkdir($svgDir, 0777, true);
}

$diagrams = allDiagrams();

foreach ($diagrams as $key => $svg) {
    $svg->save("{$svgDir}/{$key}.svg");
}

$options = new Options;
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true); // hanya untuk nomor halaman di bawah
$options->set('defaultFont', 'DejaVu Sans');
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml(documentHtml($diagrams, $svgDir), 'UTF-8');
$dompdf->render();

// Kaki halaman ditulis setelah render karena baru di sinilah jumlah halaman
// diketahui. Sampul dilewati agar tetap bersih.
$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');

$canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font) {
    if ($pageNumber === 1) {
        return;
    }

    $canvas->line(42, 810, 553, 810, [0.85, 0.89, 0.94], 0.6);
    $canvas->text(42, 818, 'Alur Kerja Aplikasi HRIS — Cahaya Optima Karya', $font, 7.5, [0.42, 0.45, 0.5]);

    $label = "Halaman {$pageNumber} dari {$pageCount}";
    $canvas->text(553 - $fontMetrics->getTextWidth($label, $font, 7.5), 818, $label, $font, 7.5, [0.42, 0.45, 0.5]);
});

// Daftar isi menyebut nomor halaman dari urutan PAGES, bukan dari hasil cetak.
// Selisih jumlah halaman berarti ada isi yang melimpah ke lembar berikutnya dan
// nomor-nomor itu sudah tidak menunjuk ke tempat yang benar.
$pages = $canvas->get_page_count();

if ($pages !== count(PAGES)) {
    fwrite(STDERR, sprintf(
        "PERINGATAN: hasil cetak %d halaman, sedangkan PAGES mendaftar %d. Nomor di daftar isi tidak lagi tepat.
",
        $pages, count(PAGES),
    ));
}

file_put_contents(__DIR__.'/Alur-Kerja-HRIS.pdf', $dompdf->output());

echo 'Selesai: docs/Alur-Kerja-HRIS.pdf ('.$pages.' halaman, '.count($diagrams)." diagram)\n";
