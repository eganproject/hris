{{-- Navigasi antar halaman menu Absen Aplikasi HP. --}}
<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('attendance.mobile.index', ['tab' => 'review']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'review', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'review'])>Perlu ditinjau @if ($reviewCount ?? 0)({{ $reviewCount }})@endif</a>
    <a href="{{ route('attendance.mobile.index', ['tab' => 'all']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'all', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'all'])>Semua absen</a>
    <a href="{{ route('attendance.mobile.index', ['tab' => 'rejected']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'rejected', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'rejected'])>Ditolak sistem</a>
    <a href="{{ route('attendance.mobile.devices') }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $active === 'devices', 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $active !== 'devices'])>HP terdaftar @if ($pendingDevices ?? 0)({{ $pendingDevices }} menunggu)@endif</a>
</div>
