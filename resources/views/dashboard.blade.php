<x-layouts.app title="Dashboard - {{ config('app.name', 'HRIS') }}" heading="Dashboard">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Overview</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">HRIS Dashboard</h1>
            </div>
            <div class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                System ready
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($metrics as $metric)
                <x-stat-card :label="$metric['label']" :value="number_format($metric['value'])" :tone="$metric['tone']">
                    <x-icon :name="$metric['icon']" class="size-5"/>
                </x-stat-card>
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_360px]">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950">Modules</h2>
                        <p class="mt-1 text-sm text-gray-500">Core HR work areas</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 divide-y divide-gray-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                    @foreach ($modules->chunk(3) as $chunk)
                        <div class="divide-y divide-gray-100">
                            @foreach ($chunk as $module)
                                <a href="{{ $module['route'] ? route($module['route']) : '#' }}" class="block p-5 transition hover:bg-gray-50">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-gray-950">{{ $module['name'] }}</p>
                                            <p class="mt-1 text-sm leading-6 text-gray-500">{{ $module['description'] }}</p>
                                        </div>
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                            {{ number_format($module['count']) }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-950">Quick Actions</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @can('employees.create')
                            <a href="{{ route('employees.create') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">New Employee</a>
                        @endcan
                        @can('employees.create')
                            <a href="#" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Import Data</a>
                        @endcan
                        @can('attendance.create')
                            <a href="#" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Shift Plan</a>
                        @endcan
                        @canany(['employees.view', 'attendance.view'])
                            <a href="#" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reports</a>
                        @endcanany
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-950">Session</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">User</dt>
                            <dd class="font-medium text-gray-950">{{ auth()->user()->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Email</dt>
                            <dd class="truncate font-medium text-gray-950">{{ auth()->user()->email }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Guard</dt>
                            <dd class="font-medium text-gray-950">web</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </section>
    </div>
</x-layouts.app>
