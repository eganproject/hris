{{-- Navigasi antar halaman menu Tablet Absensi Wajah. --}}
<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('attendance.tablets.index') }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'overview', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'overview'])>Tablet &amp; data wajah</a>
    <a href="{{ route('attendance.tablets.logs', ['tab' => 'all']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'all', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'all'])>Riwayat absen</a>
    <a href="{{ route('attendance.tablets.logs', ['tab' => 'flagged']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'flagged', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'flagged'])>Bertanda</a>
    <a href="{{ route('attendance.tablets.logs', ['tab' => 'other']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'other', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'other'])>Diabaikan / dibatalkan</a>
</div>
