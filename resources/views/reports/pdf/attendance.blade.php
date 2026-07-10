<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #111827; font-size: 10px; }
        h1 { margin: 0 0 2px; font-size: 15px; }
        .meta { margin: 0 0 12px; color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #d1d5db; padding: 4px 6px; }
        thead th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; text-align: center; }
        th.l, td.l { text-align: left; }
        td.c { text-align: center; }
        td.r { text-align: right; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .num { color: #6b7280; }
        .sub { color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
    <h1>Rekap Kehadiran</h1>
    <p class="meta">Periode: {{ $month->translatedFormat('F Y') }}
        @if ($branchName) &middot; Lokasi: {{ $branchName }} @endif
        @if ($departmentName) &middot; Divisi: {{ $departmentName }} @endif
        &middot; Dicetak: {{ now()->translatedFormat('d M Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th class="l">Karyawan</th>
                <th>Hari</th><th>Hadir</th><th>Telat</th><th>Plg Cepat</th>
                <th>Alfa</th><th>Cuti</th><th>Sakit</th>
                <th>Total Telat</th><th>Jam Kerja</th><th>Lembur Disetujui</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $e = $row['employee']; @endphp
                <tr>
                    <td class="l">{{ $e->full_name }}<br><span class="sub">{{ $e->employee_number }} &middot; {{ $e->department?->name ?? '—' }}</span></td>
                    <td class="c">{{ $row['total_hari'] }}</td>
                    <td class="c">{{ $row['hadir'] }}</td>
                    <td class="c">{{ $row['terlambat'] }}</td>
                    <td class="c">{{ $row['pulang_cepat'] }}</td>
                    <td class="c">{{ $row['alfa'] }}</td>
                    <td class="c">{{ $row['cuti'] }}</td>
                    <td class="c">{{ $row['sakit'] }}</td>
                    <td class="r">{{ $row['terlambat_menit'] }} m</td>
                    <td class="r">{{ intdiv($row['kerja_menit'], 60) }}j {{ $row['kerja_menit'] % 60 }}m</td>
                    <td class="r">{{ intdiv($row['lembur_menit'], 60) }}j {{ $row['lembur_menit'] % 60 }}m</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center; padding:16px; color:#9ca3af;">Belum ada data kehadiran pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
