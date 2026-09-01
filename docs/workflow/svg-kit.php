<?php

/**
 * Perkakas menggambar diagram alur sebagai berkas SVG.
 *
 * Diagram ditempel ke PDF lewat <img src="...svg">: dompdf tidak membaca <svg>
 * inline, tetapi menggambar berkas SVG dengan baik. Lebar <img> selalu disetel
 * dalam pt dan sama dengan lebar viewBox, sehingga 1 satuan SVG = 1 pt — ukuran
 * huruf di sini berarti persis sebesar itu di atas kertas.
 *
 * Catatan: php-svg-lib mengabaikan font-weight, jadi tidak ada teks tebal di
 * dalam diagram. Penekanan dibuat lewat warna, ukuran, dan bidang isian.
 */
final class Svg
{
    /** Palet gaya kotak: [isian, garis, warna teks]. */
    public const PALETTE = [
        'start' => ['#e2e8f0', '#94a3b8', '#0f172a'],
        'emp' => ['#eff6ff', '#60a5fa', '#1e3a8a'],
        'sup' => ['#f5f3ff', '#a78bfa', '#4c1d95'],
        'hr' => ['#fff7ed', '#fb923c', '#7c2d12'],
        'sys' => ['#ecfdf5', '#34d399', '#065f46'],
        'data' => ['#f1f5f9', '#64748b', '#1e293b'],
        'dec' => ['#fefce8', '#eab308', '#713f12'],
        'ok' => ['#dcfce7', '#22c55e', '#14532d'],
        'no' => ['#fee2e2', '#f87171', '#7f1d1d'],
        'note' => ['#fffbeb', '#fcd34d', '#78350f'],
        'plain' => ['#ffffff', '#cbd5e1', '#334155'],
    ];

    public const LINE = '#94a3b8';

    public const LINE_TEXT = '#475569';

    /** @var list<string> */
    private array $out = [];

    private static ?\Dompdf\FontMetrics $metrics = null;

    private static mixed $font = null;

    public function __construct(public readonly float $w, public readonly float $h) {}

    /* ---------------------------------------------------------------- teks */

    /** Lebar teks dalam pt, diukur dengan metrik font yang sama dipakai dompdf. */
    public static function textWidth(string $text, float $size): float
    {
        if (self::$metrics === null) {
            $dompdf = new \Dompdf\Dompdf(['defaultFont' => 'DejaVu Sans']);
            self::$metrics = $dompdf->getFontMetrics();
            self::$font = self::$metrics->getFont('DejaVu Sans', 'normal');
        }

        return self::$metrics->getTextWidth($text, self::$font, $size);
    }

    /**
     * Pecah teks agar muat pada lebar tertentu. Tanda "|" memaksa baris baru.
     *
     * @return list<string>
     */
    public static function wrap(string $text, float $size, float $maxWidth): array
    {
        $lines = [];

        foreach (explode('|', $text) as $paragraph) {
            $current = '';

            foreach (preg_split('/\s+/', trim($paragraph)) as $word) {
                $candidate = $current === '' ? $word : $current.' '.$word;

                // Faktor aman 1,05: php-svg-lib menggambar teks sedikit lebih lebar
                // daripada perkiraan metrik dompdf, dan selisih itu cukup untuk
                // membuat baris terakhir meluber keluar kotak.
                if ($current !== '' && self::textWidth($candidate, $size) * 1.05 > $maxWidth) {
                    $lines[] = $current;
                    $current = $word;

                    continue;
                }

                $current = $candidate;
            }

            $lines[] = $current;
        }

        return $lines;
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function text(float $x, float $y, string $text, float $size = 9, string $color = '#0f172a', string $anchor = 'middle'): static
    {
        $this->out[] = sprintf(
            '<text x="%.2f" y="%.2f" font-family="DejaVu Sans" font-size="%.2f" fill="%s" text-anchor="%s">%s</text>',
            $x, $y, $size, $color, $anchor, self::esc($text),
        );

        return $this;
    }

    /**
     * Beberapa baris teks yang dipusatkan secara vertikal pada titik $cy.
     *
     * @param  list<string>  $lines
     */
    public function lines(float $cx, float $cy, array $lines, float $size = 9, string $color = '#0f172a', string $anchor = 'middle'): static
    {
        $leading = $size * 1.28;
        $top = $cy - (count($lines) - 1) * $leading / 2;

        foreach (array_values($lines) as $i => $line) {
            // +0.34em: menggeser dari garis tengah ke garis dasar huruf, supaya
            // blok teksnya benar-benar terlihat di tengah kotak.
            $this->text($cx, $top + $i * $leading + $size * 0.34, $line, $size, $color, $anchor);
        }

        return $this;
    }

    /* --------------------------------------------------------------- bentuk */

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function style(string $kind): array
    {
        return self::PALETTE[$kind] ?? self::PALETTE['plain'];
    }

    /**
     * Kotak proses. $kind memilih warna dari PALETTE; $dashed menandai langkah
     * yang dikerjakan sistem tanpa campur tangan orang.
     *
     * @return array<string, float>
     */
    public function box(float $x, float $y, float $w, float $h, string $text, string $kind = 'plain', float $size = 9, float $radius = 5, bool $dashed = false, ?string $tag = null): array
    {
        [$fill, $stroke, $ink] = self::style($kind);

        $this->out[] = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s" stroke="%s" stroke-width="1"%s/>',
            $x, $y, $w, $h, $radius, $fill, $stroke, $dashed ? ' stroke-dasharray="3,2"' : '',
        );

        $cy = $y + $h / 2;

        if ($tag !== null) {
            $this->text($x + $w / 2, $y + 11, $tag, 6.5, $stroke);
            $cy += 5;
        }

        $this->lines($x + $w / 2, $cy, self::wrap($text, $size, $w - 18), $size, $ink);

        return $this->anchors($x, $y, $w, $h);
    }

    /** Kotak terminator (mulai/selesai) — sudutnya penuh. */
    public function pill(float $x, float $y, float $w, float $h, string $text, string $kind = 'start', float $size = 9): array
    {
        return $this->box($x, $y, $w, $h, $text, $kind, $size, $h / 2);
    }

    /** Belah ketupat keputusan. */
    public function diamond(float $cx, float $cy, float $w, float $h, string $text, float $size = 8.5): array
    {
        [$fill, $stroke, $ink] = self::style('dec');

        $this->out[] = sprintf(
            '<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s" stroke="%s" stroke-width="1"/>',
            $cx, $cy - $h / 2, $cx + $w / 2, $cy, $cx, $cy + $h / 2, $cx - $w / 2, $cy, $fill, $stroke,
        );

        $this->lines($cx, $cy, self::wrap($text, $size, $w * 0.62), $size, $ink);

        return $this->anchors($cx - $w / 2, $cy - $h / 2, $w, $h);
    }

    /** Jajar genjang: data masuk/keluar (tabel, berkas, dokumen). */
    public function data(float $x, float $y, float $w, float $h, string $text, string $kind = 'data', float $size = 9): array
    {
        [$fill, $stroke, $ink] = self::style($kind);
        $slant = 9;

        $this->out[] = sprintf(
            '<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s" stroke="%s" stroke-width="1"/>',
            $x + $slant, $y, $x + $w, $y, $x + $w - $slant, $y + $h, $x, $y + $h, $fill, $stroke,
        );

        $this->lines($x + $w / 2, $y + $h / 2, self::wrap($text, $size, $w - 2 * $slant - 16), $size, $ink);

        return $this->anchors($x, $y, $w, $h);
    }

    /**
     * @return array<string, float>
     */
    private function anchors(float $x, float $y, float $w, float $h): array
    {
        return [
            'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
            'cx' => $x + $w / 2, 'cy' => $y + $h / 2,
            'top' => $y, 'bottom' => $y + $h, 'left' => $x, 'right' => $x + $w,
        ];
    }

    /* ---------------------------------------------------------------- garis */

    private function head(float $x, float $y, string $dir, string $color): void
    {
        $s = 4.2;

        $points = match ($dir) {
            'down' => [[$x, $y], [$x - $s * 0.7, $y - $s], [$x + $s * 0.7, $y - $s]],
            'up' => [[$x, $y], [$x - $s * 0.7, $y + $s], [$x + $s * 0.7, $y + $s]],
            'right' => [[$x, $y], [$x - $s, $y - $s * 0.7], [$x - $s, $y + $s * 0.7]],
            default => [[$x, $y], [$x + $s, $y - $s * 0.7], [$x + $s, $y + $s * 0.7]],
        };

        $this->out[] = sprintf(
            '<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s"/>',
            $points[0][0], $points[0][1], $points[1][0], $points[1][1], $points[2][0], $points[2][1], $color,
        );
    }

    /** Panah lurus tegak. */
    public function arrowDown(float $x, float $y1, float $y2, ?string $label = null, string $color = self::LINE, bool $dashed = false): static
    {
        $this->out[] = sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="1"%s/>',
            $x, $y1, $x, $y2 - 3.5, $color, $dashed ? ' stroke-dasharray="3,3"' : '',
        );
        $this->head($x, $y2, 'down', $color);

        if ($label !== null) {
            $this->tag($x + 4, ($y1 + $y2) / 2 + 3, $label, 'start');
        }

        return $this;
    }

    /** Panah lurus mendatar. */
    public function arrowRight(float $x1, float $x2, float $y, ?string $label = null, string $color = self::LINE, bool $dashed = false): static
    {
        $dir = $x2 >= $x1 ? 'right' : 'left';
        $end = $x2 + ($dir === 'right' ? -3.5 : 3.5);

        $this->out[] = sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="1"%s/>',
            $x1, $y, $end, $y, $color, $dashed ? ' stroke-dasharray="3,3"' : '',
        );
        $this->head($x2, $y, $dir, $color);

        if ($label !== null) {
            $this->tag(($x1 + $x2) / 2, $y - 4, $label, 'middle');
        }

        return $this;
    }

    /**
     * Panah bersiku mengikuti daftar titik; kepala panah mengarah sesuai ruas
     * terakhir. Dipakai untuk jalur balik dan percabangan yang menyamping.
     *
     * @param  list<array{0:float,1:float}>  $points
     */
    public function elbow(array $points, ?string $label = null, string $color = self::LINE, bool $dashed = false): static
    {
        $d = '';

        foreach ($points as $i => [$x, $y]) {
            $d .= ($i === 0 ? 'M ' : ' L ').sprintf('%.2f %.2f', $x, $y);
        }

        [$px, $py] = $points[count($points) - 2];
        [$ex, $ey] = $points[count($points) - 1];

        $dir = abs($ex - $px) > abs($ey - $py)
            ? ($ex > $px ? 'right' : 'left')
            : ($ey > $py ? 'down' : 'up');

        $this->out[] = sprintf(
            '<path d="%s" fill="none" stroke="%s" stroke-width="1"%s/>',
            $d, $color, $dashed ? ' stroke-dasharray="3,3"' : '',
        );
        $this->head($ex, $ey, $dir, $color);

        if ($label !== null) {
            $mid = abs($points[1][0] - $points[0][0]) > abs($points[1][1] - $points[0][1])
                ? [($points[0][0] + $points[1][0]) / 2, $points[0][1] - 4]
                : [$points[0][0] + 4, ($points[0][1] + $points[1][1]) / 2];

            $this->tag($mid[0], $mid[1], $label, abs($points[1][0] - $points[0][0]) > abs($points[1][1] - $points[0][1]) ? 'middle' : 'start');
        }

        return $this;
    }

    /**
     * Garis bersiku tanpa kepala panah — dipakai sebagai rel yang menyatukan
     * beberapa cabang sebelum satu panah melanjutkannya.
     *
     * @param  list<array{0:float,1:float}>  $points
     */
    public function link(array $points, string $color = self::LINE): static
    {
        $d = '';

        foreach ($points as $i => [$x, $y]) {
            $d .= ($i === 0 ? 'M ' : ' L ').sprintf('%.2f %.2f', $x, $y);
        }

        $this->out[] = sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="1"/>', $d, $color);

        return $this;
    }

    /** Label kecil di atas bidang putih agar tetap terbaca saat menimpa garis. */
    public function tag(float $x, float $y, string $text, string $anchor = 'middle', string $color = self::LINE_TEXT, float $size = 7.5): static
    {
        $w = self::textWidth($text, $size) + 4;
        $left = match ($anchor) {
            'start' => $x - 2,
            'end' => $x - $w + 2,
            default => $x - $w / 2,
        };

        $this->out[] = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="#ffffff"/>',
            $left, $y - $size * 0.85, $w, $size * 1.18,
        );

        return $this->text($x, $y, $text, $size, $color, $anchor);
    }

    /* -------------------------------------------------------------- bingkai */

    /** Bidang latar berjudul: dipakai sebagai tahapan besar dalam peta alur. */
    public function band(float $x, float $y, float $w, float $h, string $title, string $fill = '#f8fafc', string $stroke = '#e2e8f0', string $ink = '#64748b', float $size = 8): static
    {
        $this->out[] = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="6" fill="%s" stroke="%s" stroke-width="1"/>',
            $x, $y, $w, $h, $fill, $stroke,
        );

        if ($title !== '') {
            $this->text($x + 9, $y + 13, $title, $size, $ink, 'start');
        }

        return $this;
    }

    /** Judul jalur pelaku di kepala kolom. */
    public function laneHeader(float $x, float $y, float $w, string $title, string $kind): static
    {
        [$fill, $stroke, $ink] = self::style($kind);
        $this->out[] = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="18" rx="4" fill="%s" stroke="%s" stroke-width="1"/>',
            $x, $y, $w, $fill, $stroke,
        );

        return $this->text($x + $w / 2, $y + 12.4, $title, 8.5, $ink);
    }

    public function laneDivider(float $x, float $y1, float $y2): static
    {
        $this->out[] = sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="2,3"/>',
            $x, $y1, $x, $y2,
        );

        return $this;
    }

    /** Catatan di dalam diagram. Mengembalikan tingginya agar bisa ditumpuk. */
    public function note(float $x, float $y, float $w, string $text, float $size = 7.5): float
    {
        $lines = self::wrap($text, $size, $w - 26);
        $h = count($lines) * $size * 1.4 + 11;

        [$fill, $stroke, $ink] = self::style('note');
        $this->out[] = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="4" fill="%s" stroke="%s" stroke-width="1"/>',
            $x, $y, $w, $h, $fill, $stroke,
        );
        $this->out[] = sprintf(
            '<rect x="%.2f" y="%.2f" width="3" height="%.2f" fill="%s"/>',
            $x, $y, $h, $stroke,
        );

        foreach ($lines as $i => $line) {
            $this->text($x + 11, $y + 13 + $i * $size * 1.4, $line, $size, $ink, 'start');
        }

        return $h;
    }

    public function raw(string $markup): static
    {
        $this->out[] = $markup;

        return $this;
    }

    public function render(): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.2f" height="%.2f" viewBox="0 0 %.2f %.2f">'
            .'<rect width="%.2f" height="%.2f" fill="#ffffff"/>%s</svg>',
            $this->w, $this->h, $this->w, $this->h, $this->w, $this->h, implode('', $this->out),
        );
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->render());
    }
}
