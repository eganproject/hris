<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'HRIS') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f5f7fa] font-sans text-[13px] text-gray-800 antialiased">
        <div class="page-loading-indicator" data-page-loading hidden aria-hidden="true"></div>

        @if (session('status'))
            <div data-flash-notification data-type="success" data-message="{{ session('status') }}" hidden></div>
        @endif

        <div class="app-loading-overlay" data-loading-overlay hidden>
            <div class="app-loading-card" role="status" aria-live="polite">
                <div class="app-loading-spinner" aria-hidden="true"></div>
                <div>
                    <p class="app-loading-title" data-loading-title>Memproses data...</p>
                    <p class="app-loading-message" data-loading-message>Mohon tunggu sebentar.</p>
                </div>
            </div>
        </div>

        <div class="admin-shell min-h-screen">
            <div class="mobile-nav-overlay" data-mobile-nav-overlay aria-hidden="true"></div>
            <aside class="admin-sidebar flex flex-col border-r border-[#e5e7eb] bg-[#f8fafc]">
                <div class="sidebar-brand flex h-[52px] shrink-0 items-center gap-2.5 border-b border-[#e5e7eb] px-4">
                    <div class="flex size-7 shrink-0 items-center justify-center rounded bg-primary text-[10px] font-semibold text-white">
                        CO
                    </div>
                    <div class="sidebar-brand-text min-w-0">
                        <p class="truncate text-[13px] font-semibold text-gray-900">{{ config('app.name', 'HRIS') }}</p>
                        <p class="text-[11px] text-gray-500">Admin Workspace</p>
                    </div>
                </div>

                <nav class="sidebar-nav min-h-0 flex-1 space-y-4 overflow-y-auto px-2.5 py-4">
                    @can('dashboard.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Utama</p>
                            <div class="mt-1.5">
                                <a href="{{ route('dashboard') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('dashboard'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('dashboard')]) title="Dashboard">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-6h6v6"></path></svg>
                                    <span class="sidebar-label truncate">Dashboard</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @can('employees.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Karyawan</p>
                            <div class="mt-1.5 space-y-0.5">
                                <a href="{{ route('employees.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('employees.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('employees.*')]) title="Data Karyawan">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <span class="sidebar-label truncate">Data Karyawan</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @can('organization.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Organization</p>
                            <div class="mt-1.5 space-y-0.5">
                                <a href="{{ route('organization.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('organization.index'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('organization.index')]) title="Overview Organization">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"></path></svg>
                                    <span class="sidebar-label truncate">Overview</span>
                                </a>
                                <a href="{{ route('organization.branches.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('organization.branches.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('organization.branches.*')]) title="Lokasi Kerja">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11Z"></path><circle cx="12" cy="10" r="2"></circle></svg>
                                    <span class="sidebar-label truncate">Lokasi Kerja</span>
                                </a>
                                <a href="{{ route('organization.departments.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('organization.departments.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('organization.departments.*')]) title="Divisi">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
                                    <span class="sidebar-label truncate">Divisi</span>
                                </a>
                                <a href="{{ route('organization.job-positions.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('organization.job-positions.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('organization.job-positions.*')]) title="Jabatan">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span class="sidebar-label truncate">Jabatan</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @can('attendance.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Attendance</p>
                            <div class="mt-1.5 space-y-0.5">
                                <a href="{{ route('attendance.daily.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.daily.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.daily.*')]) title="Absensi Harian">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                                    <span class="sidebar-label truncate">Absensi Harian</span>
                                </a>
                                <a href="{{ route('attendance.devices.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => (request()->routeIs('attendance.devices.*') && ! request()->routeIs('attendance.devices.monitor')) || request()->routeIs('attendance.punches.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => (! request()->routeIs('attendance.devices.*') || request()->routeIs('attendance.devices.monitor')) && ! request()->routeIs('attendance.punches.*')]) title="Perangkat Absensi">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"></rect><path d="M12 6a3 3 0 0 1 3 3c0 1.5-1 2-1 3.5M12 18h.01"></path></svg>
                                    <span class="sidebar-label truncate">Perangkat Absensi</span>
                                </a>
                                <a href="{{ route('attendance.devices.monitor') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.devices.monitor'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.devices.monitor')]) title="Monitor Mesin">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h4l3 8 4-16 3 8h4"></path></svg>
                                    <span class="sidebar-label truncate">Monitor Mesin</span>
                                </a>
                                <a href="{{ route('attendance.shifts.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.shifts.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.shifts.*')]) title="Shift Kerja">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                                    <span class="sidebar-label truncate">Shift Kerja</span>
                                </a>
                                <a href="{{ route('attendance.holidays.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.holidays.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.holidays.*')]) title="Hari Libur">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4M16 2v4M3 10h18"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect></svg>
                                    <span class="sidebar-label truncate">Hari Libur</span>
                                </a>
                                <a href="{{ route('attendance.schedule-patterns.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.schedule-patterns.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.schedule-patterns.*')]) title="Pola Jadwal">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M8 4v3M16 4v3"></path></svg>
                                    <span class="sidebar-label truncate">Pola Jadwal</span>
                                </a>
                                <a href="{{ route('attendance.schedules.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.schedules.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.schedules.*')]) title="Jadwal Kerja">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M8 4v3M16 4v3"></path><path d="M7 13h2v2H7zM15 13h2v2h-2z"></path></svg>
                                    <span class="sidebar-label truncate">Jadwal Kerja</span>
                                </a>
                                <a href="{{ route('attendance.leave.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.leave.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.leave.*')]) title="Cuti & Izin">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="m9 15 2 2 4-4"></path></svg>
                                    <span class="sidebar-label truncate">Cuti & Izin</span>
                                </a>
                                <a href="{{ route('attendance.leave-types.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.leave-types.*') || request()->routeIs('attendance.leave-balances.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! (request()->routeIs('attendance.leave-types.*') || request()->routeIs('attendance.leave-balances.*'))]) title="Jenis & Kuota Cuti">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v4H4z"></path><path d="M4 12h10"></path><path d="M4 16h10"></path><path d="M4 20h6"></path><circle cx="18" cy="16" r="3"></circle></svg>
                                    <span class="sidebar-label truncate">Jenis & Kuota Cuti</span>
                                </a>
                                <a href="{{ route('attendance.corrections.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.corrections.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.corrections.*')]) title="Koreksi Absensi">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                                    <span class="sidebar-label truncate">Koreksi Absensi</span>
                                </a>
                                <a href="{{ route('attendance.overtime.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.overtime.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.overtime.*')]) title="Lembur">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v4l3 2"></path></svg>
                                    <span class="sidebar-label truncate">Lembur</span>
                                </a>
                                <a href="{{ route('attendance.swaps.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.swaps.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.swaps.*')]) title="Tukar Jadwal">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3 4 7l4 4"></path><path d="M4 7h16"></path><path d="m16 21 4-4-4-4"></path><path d="M20 17H4"></path></svg>
                                    <span class="sidebar-label truncate">Tukar Jadwal</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @can('attendance.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Laporan</p>
                            <div class="mt-1.5 space-y-0.5">
                                <a href="{{ route('reports.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('reports.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('reports.*')]) title="Laporan">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"></path><rect x="7" y="10" width="3" height="7"></rect><rect x="12" y="6" width="3" height="11"></rect><rect x="17" y="13" width="3" height="4"></rect></svg>
                                    <span class="sidebar-label truncate">Laporan</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @canany(['leave.request', 'attendance.correction', 'schedule.swap', 'overtime.request'])
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Self-service</p>
                            <div class="mt-1.5 space-y-0.5">
                                @can('leave.request')
                                    <a href="{{ route('my-leave.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('my-leave.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('my-leave.*')]) title="Cuti Saya">
                                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4M16 2v4M3 10h18"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M8 14h.01M12 14h.01M16 14h.01"></path></svg>
                                        <span class="sidebar-label truncate">Cuti Saya</span>
                                    </a>
                                @endcan
                                @can('attendance.correction')
                                    <a href="{{ route('my-attendance.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('my-attendance.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('my-attendance.*')]) title="Absensi Saya">
                                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                                        <span class="sidebar-label truncate">Absensi Saya</span>
                                    </a>
                                @endcan
                                @can('schedule.swap')
                                    <a href="{{ route('my-schedule.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('my-schedule.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('my-schedule.*')]) title="Tukar Jadwal">
                                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3 4 7l4 4"></path><path d="M4 7h16"></path><path d="m16 21 4-4-4-4"></path><path d="M20 17H4"></path></svg>
                                        <span class="sidebar-label truncate">Tukar Jadwal</span>
                                    </a>
                                @endcan
                                @can('overtime.request')
                                    <a href="{{ route('my-overtime.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('my-overtime.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('my-overtime.*')]) title="Lembur Saya">
                                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v4l3 2"></path></svg>
                                        <span class="sidebar-label truncate">Lembur Saya</span>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endcanany

                    @canany(['access-control.view', 'attendance.update'])
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">System</p>
                            <div class="mt-1.5 space-y-0.5">
                                @can('attendance.update')
                                    <a href="{{ route('settings.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('settings.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('settings.*')]) title="Pengaturan">
                                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path></svg>
                                        <span class="sidebar-label truncate">Pengaturan</span>
                                    </a>
                                @endcan
                                @can('access-control.view')
                                    <a href="{{ route('access-control.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('access-control.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('access-control.*')]) title="Pengaturan Akses">
                                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z"></path><path d="m9.5 12 1.8 1.8L15 10"></path></svg>
                                        <span class="sidebar-label truncate">Pengaturan Akses</span>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endcanany
                </nav>

                <div class="sidebar-footer shrink-0 space-y-2 border-t border-gray-200 bg-[#f8fafc] p-3">
                    <button type="button" data-sidebar-toggle class="hidden w-full items-center justify-center gap-2 rounded border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] font-medium text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/20 lg:flex" aria-label="Toggle sidebar" aria-pressed="false">
                        <svg class="sidebar-toggle-icon size-4 shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 5h16M4 12h16M4 19h16"></path>
                            <path d="m15 9-3 3 3 3"></path>
                        </svg>
                        <span class="sidebar-label">Perkecil</span>
                    </button>

                    <div class="sidebar-user-card flex items-center gap-2.5 rounded bg-white p-2 ring-1 ring-gray-200">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded bg-primary text-[11px] font-semibold text-white">
                            {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                        </div>
                        <div class="sidebar-user-details min-w-0">
                            <p class="truncate text-[12px] font-medium text-gray-900">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[11px] text-gray-500">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="admin-content min-w-0">
                <header class="sticky top-0 z-10 flex h-[52px] items-center justify-between border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" data-mobile-nav-toggle class="flex size-9 shrink-0 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/20 lg:hidden" aria-label="Buka menu">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
                        </button>
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold text-gray-900">{{ $heading ?? 'Dashboard' }}</p>
                            <x-breadcrumbs />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @php
                            $notifUser = auth()->user();
                            $unreadCount = $notifUser->unreadNotifications()->count();
                            $recentNotifs = $notifUser->notifications()->latest()->limit(8)->get();
                        @endphp
                        <div class="relative" data-dropdown>
                            <button type="button" data-dropdown-trigger aria-expanded="false" class="relative flex size-9 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/20" title="Notifikasi" aria-label="Notifikasi">
                                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                                <span data-notif-count data-notif-poll="{{ route('notifications.count') }}" @unless ($unreadCount > 0) hidden @endunless class="absolute -right-1 -top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                            </button>
                            <div data-dropdown-menu hidden class="w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                                    <p class="text-sm font-semibold text-gray-900">Notifikasi</p>
                                    @if ($unreadCount > 0)
                                        <form method="POST" action="{{ route('notifications.read-all') }}" data-no-confirm="true" data-no-loading="true">@csrf<button type="submit" class="text-xs font-medium text-primary hover:underline">Tandai semua dibaca</button></form>
                                    @endif
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    @forelse ($recentNotifs as $notif)
                                        <a href="{{ route('notifications.read', $notif->id) }}" @class(['flex flex-col gap-0.5 border-b border-gray-50 px-4 py-2.5 transition hover:bg-gray-50', 'bg-primary-soft' => is_null($notif->read_at)])>
                                            <span class="flex items-center gap-2 text-[13px] font-semibold text-gray-900">
                                                @if (is_null($notif->read_at))<span class="size-1.5 shrink-0 rounded-full bg-red-500"></span>@endif
                                                {{ $notif->data['title'] ?? 'Notifikasi' }}
                                            </span>
                                            <span class="text-xs text-gray-600">{{ $notif->data['message'] ?? '' }}</span>
                                            <span class="text-[11px] text-gray-400">{{ $notif->created_at->diffForHumans() }}</span>
                                        </a>
                                    @empty
                                        <p class="px-4 py-6 text-center text-sm text-gray-400">Belum ada notifikasi.</p>
                                    @endforelse
                                </div>
                                <a href="{{ route('notifications.index') }}" class="block border-t border-gray-100 px-4 py-2.5 text-center text-xs font-medium text-gray-600 hover:bg-gray-50">Lihat semua notifikasi</a>
                            </div>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-md px-1.5 py-1 transition hover:bg-gray-50" title="Profil Saya">
                            <span class="flex size-8 items-center justify-center rounded-full bg-primary-soft text-[11px] font-semibold text-gray-700">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="hidden text-right sm:block">
                                <span class="block text-[12px] font-medium text-gray-900">{{ auth()->user()->name }}</span>
                                <span class="block text-[11px] text-gray-500">Profil Saya</span>
                            </span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] font-medium text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/20">
                                Logout
                            </button>
                        </form>
                    </div>
                </header>

                <main class="px-4 py-4 sm:px-5 lg:px-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
