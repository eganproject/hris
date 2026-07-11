<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #111827; font-size: 9px; }
        h1 { margin: 0 0 2px; font-size: 15px; }
        .meta { margin: 0 0 12px; color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #d1d5db; padding: 3px 5px; }
        thead th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; text-align: center; }
        th.l, td.l { text-align: left; }
        td.c { text-align: center; }
        td.r { text-align: right; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .sub { color: #9ca3af; font-size: 7px; }
        .muted { color: #9ca3af; }
    </style>
</head>
<body>
    <h1>Log Absensi</h1>
    <p class="meta">Periode: {{ $month->translatedFormat('F Y') }}
        @if ($branchName) &middot; Lokasi: {{ $branchName }} @endif
        @if ($departmentName) &middot; Divisi: {{ $departmentName }} @endif
        &middot; {{ $rows->count() }} baris &middot; Dicetak: {{ now()->translatedFormat('d M Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th class="l">Tanggal</th>
                <th class="l">Karyawan</th>
                <th class="l">Divisi</th>
                <th>Shift</th>
                <th>Masuk</th>
                <th>Keluar</th>
                <th>Telat</th>
                <th>Plg Cepat</th>
                <th>Jam Kerja</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="l">{{ $r->work_date->translatedFormat('d M Y') }}</td>
                    <td class="l">{{ $r->employee?->full_name ?? '—' }}<br><span class="sub">{{ $r->employee?->employee_number ?? '—' }}</span></td>
                    <td class="l">{{ $r->employee?->department?->name ?? '—' }}</td>
                    <td class="c">{{ $r->shift?->code ?? '—' }}</td>
                    <td class="c">{{ $r->clock_in ? $r->clock_in->format('H:i') : '–' }}</td>
                    <td class="c">{{ $r->clock_out ? $r->clock_out->format('H:i') : '–' }}</td>
                    <td class="r">{{ $r->late_minutes }} m</td>
                    <td class="r">{{ $r->early_leave_minutes }} m</td>
                    <td class="r">{{ intdiv($r->work_minutes, 60) }}j {{ $r->work_minutes % 60 }}m</td>
                    <td class="c">{{ $r->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center; padding:16px; color:#9ca3af;">Belum ada data kehadiran pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
