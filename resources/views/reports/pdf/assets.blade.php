<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #111827; font-size: 9px; }
        h1 { margin: 0 0 2px; font-size: 15px; }
        h2 { margin: 14px 0 4px; font-size: 11px; }
        .meta { margin: 0 0 10px; color: #6b7280; font-size: 9px; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #d1d5db; padding: 3px 5px; }
        thead th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; text-align: center; }
        th.l, td.l { text-align: left; }
        td.c { text-align: center; }
        td.r { text-align: right; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tfoot td { background: #f3f4f6; font-weight: bold; }
        .sub { color: #9ca3af; font-size: 7px; }
    </style>
</head>
<body>
    <h1>Register Aset</h1>
    <p class="meta">
        @foreach ($filterLabels as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last) &middot; @endif
        @endforeach
        <br>Dicetak: {{ now()->translatedFormat('d M Y H:i') }}
        &middot; Jumlah aset: {{ number_format($summary['total']) }}
        &middot; Nilai perolehan: Rp {{ number_format($summary['value'], 0, ',', '.') }}
        {{-- Dua angka, bukan satu "sedang terpakai": hanya Dipegang yang punya nama
             penanggung jawab, dan cetakan inilah yang dibawa saat stock opname. --}}
        &middot; Dipegang karyawan: {{ number_format($summary['assigned']) }}
        &middot; Dipakai bersama: {{ number_format($summary['in_use']) }}
    </p>

    <h2>Rekap per {{ $groupLabel }}</h2>
    <table>
        <thead>
            <tr>
                <th class="l">{{ $groupLabel }}</th>
                <th>Jumlah Aset</th>
                <th>Nilai Perolehan</th>
                <th>% Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr>
                    <td class="l">{{ $group['label'] }}</td>
                    <td class="r">{{ number_format($group['count']) }}</td>
                    <td class="r">{{ number_format($group['value'], 0, ',', '.') }}</td>
                    <td class="r">{{ $summary['total'] > 0 ? number_format($group['count'] / $summary['total'] * 100, 1) : '0,0' }}%</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; padding:12px; color:#9ca3af;">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
            @endforelse
        </tbody>
        @if ($groups->isNotEmpty())
            <tfoot>
                <tr>
                    <td class="l">Total</td>
                    <td class="r">{{ number_format($summary['total']) }}</td>
                    <td class="r">{{ number_format($summary['value'], 0, ',', '.') }}</td>
                    <td class="r">100,0%</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Padanan tampilan tercollapse di layar. Di kertas tidak ada yang bisa diklik,
         jadi bentuknya tabel tersendiri: barang apa yang menumpuk dan berapa
         banyak, sebelum masuk ke daftar per unit di bawahnya. --}}
    <h2>Ringkas per Nama Aset</h2>
    <table>
        <thead>
            <tr>
                <th class="l">Nama Aset</th>
                <th>Jumlah Unit</th>
                <th>Nilai Perolehan</th>
                <th class="l">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($names as $name)
                <tr>
                    <td class="l">{{ $name['name'] }}</td>
                    <td class="r">{{ number_format($name['units']) }}</td>
                    <td class="r">{{ number_format($name['value'], 0, ',', '.') }}</td>
                    <td class="l">{{ $name['spellings'] > 1 ? $name['spellings'].' ejaan berbeda' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; padding:12px; color:#9ca3af;">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
            @endforelse
        </tbody>
        @if ($names->isNotEmpty())
            <tfoot>
                <tr>
                    <td class="l">Total {{ number_format($names->count()) }} nama</td>
                    <td class="r">{{ number_format($summary['total']) }}</td>
                    <td class="r">{{ number_format($summary['value'], 0, ',', '.') }}</td>
                    <td class="l"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Versi datarnya tetap dicetak utuh: yang tercollapse untuk dibaca, yang ini
         untuk ditelusuri unit per unit saat barangnya dihitung di gudang. --}}
    <h2>Daftar Aset (rinci per unit)</h2>
    <table>
        <thead>
            <tr>
                <th class="l">Kode & Nama</th>
                <th class="l">Kategori</th>
                <th class="l">Lokasi</th>
                <th class="l">Divisi</th>
                <th>Status</th>
                <th>Kondisi</th>
                <th class="l">Pemegang</th>
                <th>Nilai Perolehan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $asset)
                <tr>
                    <td class="l">{{ $asset->asset_code }}<br><span class="sub">{{ $asset->name }}</span></td>
                    <td class="l">{{ $asset->category?->name ?? '—' }}</td>
                    <td class="l">{{ $asset->currentBranch?->name ?? '—' }}@if ($asset->owning_branch_id !== $asset->current_branch_id)<br><span class="sub">milik {{ $asset->owningBranch?->name ?? '—' }}</span>@endif</td>
                    <td class="l">{{ $asset->department?->name ?? '—' }}</td>
                    <td class="c">{{ $asset->status_label }}</td>
                    <td class="c">{{ $asset->condition_label }}</td>
                    <td class="l">{{ $asset->currentAssignment?->employee?->full_name ?? ($asset->status?->isSharedUse() ? 'Pakai bersama' : '—') }}</td>
                    <td class="r">{{ $asset->acquisition_cost === null ? '—' : number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; padding:16px; color:#9ca3af;">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
