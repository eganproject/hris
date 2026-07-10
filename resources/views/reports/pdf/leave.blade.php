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
        .sub { color: #9ca3af; font-size: 8px; }
        .rem { color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
    <h1>Rekap Cuti</h1>
    <p class="meta">Tahun: {{ $year }}
        @if ($branchName) &middot; Lokasi: {{ $branchName }} @endif
        @if ($departmentName) &middot; Divisi: {{ $departmentName }} @endif
        &middot; Dicetak: {{ now()->translatedFormat('d M Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th class="l">Karyawan</th>
                @foreach ($types as $type)
                    <th>{{ $type->name }}</th>
                @endforeach
                <th>Total Hari</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $e = $row['employee']; @endphp
                <tr>
                    <td class="l">{{ $e->full_name }}<br><span class="sub">{{ $e->employee_number }} &middot; {{ $e->department?->name ?? '—' }}</span></td>
                    @foreach ($types as $type)
                        @php $cell = $row['cells'][$type->id]; @endphp
                        <td class="c">{{ $cell['used'] }}@if ($cell['remaining'] !== null)<br><span class="rem">sisa {{ $cell['remaining'] }}</span>@endif</td>
                    @endforeach
                    <td class="r">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $types->count() + 2 }}" style="text-align:center; padding:16px; color:#9ca3af;">Belum ada data cuti pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
