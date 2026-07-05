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
            <aside class="admin-sidebar hidden border-r border-[#e5e7eb] bg-[#f8fafc] lg:flex lg:flex-col">
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
                                <a href="{{ route('attendance.shifts.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('attendance.shifts.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('attendance.shifts.*')]) title="Shift Kerja">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                                    <span class="sidebar-label truncate">Shift Kerja</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @can('payroll.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">Payroll</p>
                            <div class="mt-1.5">
                                <a href="{{ route('payroll.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('payroll.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('payroll.*')]) title="Gaji / Payroll">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"></rect><circle cx="12" cy="12" r="2.5"></circle><path d="M6 9h.01M18 15h.01"></path></svg>
                                    <span class="sidebar-label truncate">Gaji / Payroll</span>
                                </a>
                            </div>
                        </div>
                    @endcan

                    @can('access-control.view')
                        <div>
                            <p class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase text-gray-400">System</p>
                            <div class="mt-1.5">
                                <a href="{{ route('access-control.index') }}" @class(['sidebar-nav-link flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'bg-white text-gray-950 shadow-xs ring-1 ring-gray-200' => request()->routeIs('access-control.*'), 'text-gray-600 hover:bg-white hover:text-gray-950' => ! request()->routeIs('access-control.*')]) title="Pengaturan Akses">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z"></path><path d="m9.5 12 1.8 1.8L15 10"></path></svg>
                                    <span class="sidebar-label truncate">Pengaturan Akses</span>
                                </a>
                            </div>
                        </div>
                    @endcan
                </nav>

                <div class="sidebar-footer shrink-0 space-y-2 border-t border-gray-200 bg-[#f8fafc] p-3">
                    <button type="button" data-sidebar-toggle class="flex w-full items-center justify-center gap-2 rounded border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] font-medium text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="Toggle sidebar" aria-pressed="false">
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
                        <div class="flex size-8 items-center justify-center rounded bg-primary text-[11px] font-semibold text-white lg:hidden">
                            CO
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold text-gray-900">{{ $heading ?? 'Dashboard' }}</p>
                            <p class="hidden text-[11px] text-gray-500 sm:block">Administration workspace</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-[12px] font-medium text-gray-900">{{ auth()->user()->name }}</p>
                            <p class="text-[11px] text-gray-500">Signed in</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] font-medium text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/20">
                                Logout
                            </button>
                        </form>
                    </div>
                </header>

                <main class="px-4 py-4 sm:px-5 lg:px-6">
                    <x-breadcrumbs />
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
